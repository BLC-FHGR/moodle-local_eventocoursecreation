<?php

namespace local_eventocoursecreation;

/**
 * Value object for term timing settings
 */
class TermSettings
{
    /**
     * Constructor
     *
     * @param int $springStartDay
     * @param int $springStartMonth
     * @param int $springEndDay
     * @param int $springEndMonth
     * @param int $autumnStartDay
     * @param int $autumnStartMonth
     * @param int $autumnEndDay
     * @param int $autumnEndMonth
     */
    public function __construct(
        public readonly int $springStartDay,
        public readonly int $springStartMonth,
        public readonly int $springEndDay,
        public readonly int $springEndMonth,
        public readonly int $autumnStartDay,
        public readonly int $autumnStartMonth,
        public readonly int $autumnEndDay,
        public readonly int $autumnEndMonth
    ) {}
}
