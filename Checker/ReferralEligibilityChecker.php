<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pentarim\SyliusAffiliateBundle\Checker;

use Pentarim\SyliusAffiliateBundle\Model\AffiliateInterface;
use Pentarim\SyliusAffiliateBundle\Model\AffiliateGoalInterface;
use Pentarim\SyliusAffiliateBundle\Model\RuleInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Pentarim\SyliusAffiliateBundle\Exception\UnsupportedTypeException;

class ReferralEligibilityChecker implements ReferralEligibilityCheckerInterface
{

    /**
     * @var ServiceRegistryInterface
     */
    protected $registry;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @param ServiceRegistryInterface $registry
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(ServiceRegistryInterface $registry, EventDispatcherInterface $dispatcher)
    {
        $this->registry = $registry;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function isEligible(AffiliateGoalInterface $goal, AffiliateInterface $affiliate, $subject = null)
    {
        if (!$this->isEligibleToDates($goal)) {
            return false;
        }

        if (!$this->isEligibleToUsageLimit($goal)) {
            return false;
        }

        //if (!$this->isEligibleToAffiliate($goal, $affiliate)) {
        //    return false;
        //}

        $eligible      = true;
        $eligibleRules = false;

        if ($goal->hasRules()) {
            /* @var RuleInterface $rule */
            foreach ($goal->getRules() as $rule) {
                try {
                    if (!$this->isEligibleToRule($subject, $goal, $rule)) {
                        return false;
                    }

                    $eligibleRules = true;
                } catch (UnsupportedTypeException $exception) {
                    if (!$eligibleRules) {
                        $eligible = false;
                    }

                    continue;
                }
            }
        }

        return $eligible;
    }

    /**
     * Checks is a goal is eligible to a subject for a given rule.
     *
     * @param mixed $subject
     * @param AffiliateGoalInterface $goal
     * @param RuleInterface $rule
     * @return bool
     */
    protected function isEligibleToRule($subject, AffiliateGoalInterface $goal, RuleInterface $rule)
    {
        $checker = $this->registry->get($rule->getType());

        if ($checker->supports($subject) && $checker->isEligible($subject, $rule->getConfiguration())) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the current is between date constraints.
     *
     * @param AffiliateGoalInterface $goal
     *
     * @return Boolean
     */
    protected function isEligibleToDates(AffiliateGoalInterface $goal)
    {
        $now = new \DateTime();

        if (null !== $startsAt = $goal->getStartsAt()) {
            if ($now < $startsAt) {
                return false;
            }
        }

        if (null !== $endsAt = $goal->getEndsAt()) {
            if ($now > $endsAt) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if goal usage limit has been reached.
     *
     * @param AffiliateGoalInterface $goal
     *
     * @return Boolean
     */
    protected function isEligibleToUsageLimit(AffiliateGoalInterface $goal)
    {
        if (null !== $usageLimit = $goal->getUsageLimit()) {
            if ($goal->getUsed() >= $usageLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if goal is affiliate specific.
     *
     * @param AffiliateGoalInterface $goal
     * @see ReferrerRuleChecker
     * @return Boolean
     */
    protected function isEligibleToAffiliate(AffiliateGoalInterface $goal, AffiliateInterface $affiliate)
    {
        if ($goal->hasRules()) {
            /* @var RuleInterface $rule */
            foreach ($goal->getRules() as $rule) {
                try {
                    if ($this->isEligibleToRule($affiliate, $goal, $rule)) {
                        return true;
                    }
                } catch (UnsupportedTypeException $exception) {
                    continue;
                }
            }
        }

        return false;
    }
}
