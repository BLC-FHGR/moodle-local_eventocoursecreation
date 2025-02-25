<?php
namespace local_eventocoursecreation\valueobjects;

/**
 * Value object for term-related settings and timing configurations
 */
class TermSettings {
    /**
     * Constructor
     *
     * @param int $springTermStartDay Day of spring term start
     * @param int $springTermStartMonth Month of spring term start
     * @param int $springTermEndDay Day of spring term end
     * @param int $springTermEndMonth Month of spring term end
     * @param int $autumnTermStartDay Day of autumn term start
     * @param int $autumnTermStartMonth Month of autumn term start
     * @param int $autumnTermEndDay Day of autumn term end
     * @param int $autumnTermEndMonth Month of autumn term end
     */
    public function __construct(
        private readonly int $springTermStartDay,
        private readonly int $springTermStartMonth,
        private readonly int $springTermEndDay,
        private readonly int $springTermEndMonth,
        private readonly int $autumnTermStartDay,
        private readonly int $autumnTermStartMonth,
        private readonly int $autumnTermEndDay,
        private readonly int $autumnTermEndMonth
    ) {}

    /**
     * Get the start day of spring term
     * @return int
     */
    public function getSpringTermStartDay(): int {
        return $this->springTermStartDay;
    }

    /**
     * Get the start month of spring term
     * @return int
     */
    public function getSpringTermStartMonth(): int {
        return $this->springTermStartMonth;
    }

    /**
     * Get the end day of spring term
     * @return int
     */
    public function getSpringTermEndDay(): int {
        return $this->springTermEndDay;
    }

    /**
     * Get the end month of spring term
     * @return int
     */
    public function getSpringTermEndMonth(): int {
        return $this->springTermEndMonth;
    }

    /**
     * Get the start day of autumn term
     * @return int
     */
    public function getAutumnTermStartDay(): int {
        return $this->autumnTermStartDay;
    }

    /**
     * Get the start month of autumn term
     * @return int
     */
    public function getAutumnTermStartMonth(): int {
        return $this->autumnTermStartMonth;
    }

    /**
     * Get the end day of autumn term
     * @return int
     */
    public function getAutumnTermEndDay(): int {
        return $this->autumnTermEndDay;
    }

    /**
     * Get the end month of autumn term
     * @return int
     */
    public function getAutumnTermEndMonth(): int {
        return $this->autumnTermEndMonth;
    }

    /**
     * Get spring term start timestamp for a given year
     * 
     * @param int $year
     * @return int
     */
    public function getSpringTermStartTimestamp(int $year): int {
        return mktime(0, 0, 0, $this->springTermStartMonth, $this->springTermStartDay, $year);
    }

    /**
     * Get spring term end timestamp for a given year
     * 
     * @param int $year
     * @return int
     */
    public function getSpringTermEndTimestamp(int $year): int {
        return mktime(23, 59, 59, $this->springTermEndMonth, $this->springTermEndDay, $year);
    }

    /**
     * Get autumn term start timestamp for a given year
     * 
     * @param int $year
     * @return int
     */
    public function getAutumnTermStartTimestamp(int $year): int {
        return mktime(0, 0, 0, $this->autumnTermStartMonth, $this->autumnTermStartDay, $year);
    }

    /**
     * Get autumn term end timestamp for a given year
     * 
     * @param int $year
     * @return int
     */
    public function getAutumnTermEndTimestamp(int $year): int {
        return mktime(23, 59, 59, $this->autumnTermEndMonth, $this->autumnTermEndDay, $year);
    }

    /**
     * Convert settings to array format
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'starttimespringtermday' => $this->springTermStartDay,
            'starttimespringtermmonth' => $this->springTermStartMonth,
            'endtimespringtermday' => $this->springTermEndDay,
            'endtimespringtermmonth' => $this->springTermEndMonth,
            'starttimeautumntermday' => $this->autumnTermStartDay,
            'starttimeautumntermmonth' => $this->autumnTermStartMonth,
            'endtimeautumntermday' => $this->autumnTermEndDay,
            'endtimeautumntermmonth' => $this->autumnTermEndMonth
        ];
    }
}