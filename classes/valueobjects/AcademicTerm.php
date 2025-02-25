<?php

namespace local_eventocoursecreation\value_objects;

use local_eventocoursecreation\exceptions\ValidationException;

/**
 * Represents an academic term with its associated metadata
 * 
 * This immutable value object encapsulates the concept of an academic term,
 * including its type (Spring/Autumn), period, and associated settings. It works
 * in conjunction with TermPeriod to provide a complete representation of
 * academic scheduling.
 */
final class AcademicTerm
{
    public const TYPE_SPRING = 'spring';
    public const TYPE_AUTUMN = 'autumn';

    /**
     * @param string $type Term type (spring/autumn)
     * @param TermPeriod $period The term's time period
     * @param bool $restrictStartTime Whether to restrict creation to term start
     */
    public function __construct(
        private readonly string $type,
        private readonly TermPeriod $period,
        private readonly bool $restrictStartTime
    ) {
        if (!in_array($type, [self::TYPE_SPRING, self::TYPE_AUTUMN])) {
            throw new ValidationException("Invalid term type: {$type}");
        }
    }

    /**
     * Creates a spring term instance
     *
     * @param int $startDay Spring term start day
     * @param int $startMonth Spring term start month
     * @param int $endDay Spring term end day
     * @param int $endMonth Spring term end month
     * @param int $referenceTime Current reference time
     * @param int $eventTime Event time
     * @param bool $restrictStartTime Whether to restrict creation to term start
     * @return self
     */
    public static function createSpringTerm(
        int $startDay,
        int $startMonth,
        int $endDay,
        int $endMonth,
        int $referenceTime,
        int $eventTime,
        bool $restrictStartTime
    ): self {
        return new self(
            self::TYPE_SPRING,
            new TermPeriod($startDay, $startMonth, $endDay, $endMonth, $referenceTime, $eventTime),
            $restrictStartTime
        );
    }

    /**
     * Creates an autumn term instance
     *
     * @param int $startDay Autumn term start day
     * @param int $startMonth Autumn term start month
     * @param int $endDay Autumn term end day
     * @param int $endMonth Autumn term end month
     * @param int $referenceTime Current reference time
     * @param int $eventTime Event time
     * @param bool $restrictStartTime Whether to restrict creation to term start
     * @return self
     */
    public static function createAutumnTerm(
        int $startDay,
        int $startMonth,
        int $endDay,
        int $endMonth,
        int $referenceTime,
        int $eventTime,
        bool $restrictStartTime
    ): self {
        return new self(
            self::TYPE_AUTUMN,
            new TermPeriod($startDay, $startMonth, $endDay, $endMonth, $referenceTime, $eventTime),
            $restrictStartTime
        );
    }

    /**
     * Checks if a given time is valid for course creation in this term
     *
     * @param int $timestamp Time to check
     * @return bool
     */
    public function isValidCreationTime(int $timestamp): bool
    {
        if (!$this->period->containsTime($timestamp)) {
            return false;
        }

        if ($this->restrictStartTime) {
            // Only allow creation at the start of the term
            $termStart = $this->period->getStartTimestamp();
            $oneDayAfterStart = $termStart + (24 * 60 * 60);
            return $timestamp >= $termStart && $timestamp <= $oneDayAfterStart;
        }

        return true;
    }

    /**
     * Gets the term type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the term period
     *
     * @return TermPeriod
     */
    public function getPeriod(): TermPeriod
    {
        return $this->period;
    }

    /**
     * Checks if creation is restricted to term start
     *
     * @return bool
     */
    public function hasStartTimeRestriction(): bool
    {
        return $this->restrictStartTime;
    }
}