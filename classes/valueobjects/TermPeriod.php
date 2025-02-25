<?php

namespace local_eventocoursecreation\value_objects;

use local_eventocoursecreation\exceptions\ValidationException;

/**
 * Represents a term period in the academic calendar
 * 
 * This immutable value object encapsulates the logic for managing academic terms,
 * including their start and end dates, and provides methods for determining if
 * a given time falls within the term period.
 * 
 * The class handles the complexity of academic terms that might span across
 * year boundaries (like autumn terms) and ensures consistent date calculations
 * across the system.
 */
final class TermPeriod
{
    /**
     * @var int Unix timestamp for period start
     */
    private readonly int $startTimestamp;

    /**
     * @var int Unix timestamp for period end
     */
    private readonly int $endTimestamp;

    /**
     * Creates a new term period
     * 
     * The constructor calculates the actual start and end timestamps based on
     * the provided day/month values and reference times. It handles cases where
     * terms span across year boundaries by adjusting the year appropriately.
     *
     * @param int $startDay Day of month for period start (1-31)
     * @param int $startMonth Month for period start (1-12)
     * @param int $endDay Day of month for period end (1-31)
     * @param int $endMonth Month for period end (1-12)
     * @param int $referenceTime Current timestamp for year calculation
     * @param int $eventTime Event timestamp for year calculation
     * @throws ValidationException If date parameters are invalid
     */
    public function __construct(
        private readonly int $startDay,
        private readonly int $startMonth,
        private readonly int $endDay,
        private readonly int $endMonth,
        int $referenceTime,
        int $eventTime
    ) {
        $this->validateDateParameters();
        
        // Calculate the appropriate year based on reference and event times
        $referenceYear = (int)date('Y', $referenceTime);
        $eventYear = (int)date('Y', $eventTime);
        $year = $eventYear >= $referenceYear ? $eventYear : $referenceYear;

        // Create initial timestamps
        $start = $this->createTimestamp($this->startDay, $this->startMonth, $year);
        $end = $this->createTimestamp($this->endDay, $this->endMonth, $year);

        // Adjust for periods that cross year boundary
        if ($start > $end) {
            if ($referenceTime < $start) {
                $start = $this->createTimestamp($this->startDay, $this->startMonth, $year - 1);
            } else {
                $end = $this->createTimestamp($this->endDay, $this->endMonth, $year + 1);
            }
        }

        $this->startTimestamp = $start;
        $this->endTimestamp = $end;
    }

    /**
     * Validates that the date parameters are within valid ranges
     *
     * @throws ValidationException If any parameter is invalid
     */
    private function validateDateParameters(): void
    {
        if ($this->startDay < 1 || $this->startDay > 31) {
            throw new ValidationException("Invalid start day: {$this->startDay}");
        }
        if ($this->endDay < 1 || $this->endDay > 31) {
            throw new ValidationException("Invalid end day: {$this->endDay}");
        }
        if ($this->startMonth < 1 || $this->startMonth > 12) {
            throw new ValidationException("Invalid start month: {$this->startMonth}");
        }
        if ($this->endMonth < 1 || $this->endMonth > 12) {
            throw new ValidationException("Invalid end month: {$this->endMonth}");
        }

        // Validate day-month combinations
        $this->validateDayMonthCombination($this->startDay, $this->startMonth, 'start');
        $this->validateDayMonthCombination($this->endDay, $this->endMonth, 'end');
    }

    /**
     * Validates that a day-month combination is valid
     *
     * Takes into account different month lengths and leap years.
     *
     * @param int $day Day of month
     * @param int $month Month number
     * @param string $prefix Error message prefix
     * @throws ValidationException If combination is invalid
     */
    private function validateDayMonthCombination(int $day, int $month, string $prefix): void
    {
        $daysInMonth = [
            1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30,
            7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31
        ];

        if ($day > $daysInMonth[$month]) {
            throw new ValidationException(
                "Invalid {$prefix} date: {$day}/{$month} - month has {$daysInMonth[$month]} days"
            );
        }
    }

    /**
     * Creates a Unix timestamp for a given date at midnight
     *
     * @param int $day Day of month
     * @param int $month Month number
     * @param int $year Year
     * @return int Unix timestamp
     */
    private function createTimestamp(int $day, int $month, int $year): int
    {
        return mktime(0, 0, 0, $month, $day, $year);
    }

    /**
     * Checks if a given timestamp falls within this period
     *
     * @param int $timestamp The timestamp to check
     * @return bool True if timestamp is within period
     */
    public function containsTime(int $timestamp): bool
    {
        return $timestamp >= $this->startTimestamp && $timestamp <= $this->endTimestamp;
    }

    /**
     * Gets the period start timestamp
     *
     * @return int Unix timestamp
     */
    public function getStartTimestamp(): int
    {
        return $this->startTimestamp;
    }

    /**
     * Gets the period end timestamp
     *
     * @return int Unix timestamp
     */
    public function getEndTimestamp(): int
    {
        return $this->endTimestamp;
    }

    /**
     * Checks if this period overlaps with another period
     *
     * @param TermPeriod $other The period to check against
     * @return bool True if periods overlap
     */
    public function overlaps(TermPeriod $other): bool
    {
        return $this->startTimestamp <= $other->getEndTimestamp() 
            && $this->endTimestamp >= $other->getStartTimestamp();
    }

    /**
     * Creates a formatted string representation of the period
     *
     * @return string
     */
    public function toString(): string
    {
        return sprintf(
            '%s to %s',
            date('Y-m-d', $this->startTimestamp),
            date('Y-m-d', $this->endTimestamp)
        );
    }
}