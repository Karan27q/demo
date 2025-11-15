<?php
/**
 * Interest Calculator for Gold Finance
 *
 * Calculates simple interest using an annual percentage rate and the actual
 * number of days the loan has been outstanding (Actual/365 convention with
 * leap-year support).
 *
 * Interest = Principal × (Annual Rate / 100) × (Days Outstanding / Days in Year)
 *
 * Supported inputs:
 * - Principal amount as numeric.
 * - Annual interest rate as percentage (e.g. 12 for 12% p.a.).
 * - Start/end dates as strings or DateTime/DateTimeImmutable objects.
 */
function calculateExpectedInterest($principal, $interestRate, $startDate, $endDate = null) {
    if (!is_numeric($principal) || !is_numeric($interestRate)) {
        return 0.0;
    }

    $principal = (float) $principal;
    $interestRate = (float) $interestRate;

    if ($principal <= 0.0 || $interestRate <= 0.0) {
        return 0.0;
    }

    $start = icNormalizeToDateTime($startDate);
    if (!$start) {
        return 0.0;
    }

    if ($endDate === null) {
        $end = new DateTime();
    } else {
        $end = icNormalizeToDateTime($endDate);
        if (!$end) {
            return 0.0;
        }
    }

    // Normalise to midnight to avoid partial-day discrepancies
    $start->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    if ($end <= $start) {
        return 0.0;
    }

    $annualRate = $interestRate / 100;
    $totalInterest = 0.0;

    $current = clone $start;
    while ($current < $end) {
        $daysInYear = $current->format('L') ? 366 : 365;

        $yearEnd = clone $current;
        $yearEnd->setDate((int) $current->format('Y'), 12, 31);

        if ($yearEnd > $end) {
            $yearEnd = clone $end;
        }

        $days = $current->diff($yearEnd)->days;
        if ($yearEnd < $end) {
            $days += 1;
        }

        if ($days > 0) {
            $totalInterest += $principal * $annualRate * ($days / $daysInYear);
        }

        $current = clone $yearEnd;
        $current->modify('+1 day');
    }

    return round($totalInterest, 2);
}

/**
 * Wrapper retained for backwards compatibility.
 * Interest is now calculated using the same daily simple-interest approach.
 *
 * @param float $principal Principal amount
 * @param float $interestRate Annual interest rate (percentage)
 * @param string|DateTimeInterface $startDate Loan start date
 * @param string|DateTimeInterface|null $endDate Evaluation date (defaults to today)
 *
 * @return float
 */
function calculateExpectedInterestByCalendarMonths($principal, $interestRate, $startDate, $endDate = null) {
    return calculateExpectedInterest($principal, $interestRate, $startDate, $endDate);
}

/**
 * Normalises different date representations into a mutable DateTime instance.
 *
 * @param mixed $value
 * @return DateTime|null
 */
if (!function_exists('icNormalizeToDateTime')) {
    function icNormalizeToDateTime($value) {
        if ($value instanceof DateTimeInterface) {
            return new DateTime($value->format('Y-m-d H:i:s'), $value->getTimezone());
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTime((string) $value);
        } catch (Exception $e) {
            return null;
        }
    }
}

