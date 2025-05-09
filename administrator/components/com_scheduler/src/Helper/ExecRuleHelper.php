<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_scheduler
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Scheduler\Administrator\Helper;

use Cron\CronExpression;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper class for supporting task execution rules.
 *
 * @since  4.1.0
 * @todo   This helper should probably be merged into the {@see Task} class.
 */
class ExecRuleHelper
{
    /**
     * The execution rule type
     *
     * @var string
     * @since  4.1.0
     */
    private $type;

    /**
     * @var array
     * @since  4.1.0
     */
    private $task;

    /**
     * @var object
     * @since  4.1.0
     */
    private $rule;

    /**
     * @param   array|object  $task  A task entry
     *
     * @since  4.1.0
     */
    public function __construct($task)
    {
        $this->task = \is_array($task) ? $task : ArrayHelper::fromObject($task);
        $rule       = $this->getFromTask('cron_rules');
        $this->rule = \is_string($rule)
            ? (object) json_decode($rule)
            : (\is_array($rule) ? (object) $rule : $rule);
        $this->type = $this->rule->type;
    }

    /**
     * Get a property from the task array
     *
     * @param   string  $property  The property to get
     * @param   mixed   $default   The default value returned if property does not exist
     *
     * @return mixed
     *
     * @since  4.1.0
     */
    private function getFromTask(string $property, $default = null)
    {
        $property = ArrayHelper::getValue($this->task, $property);

        return $property ?? $default;
    }

    /**
     * @param   boolean  $string    If true, an SQL formatted string is returned.
     * @param   boolean  $basisNow  If true, the current date-time is used as the basis for projecting the next
     *                              execution.
     *
     * @return ?Date|string
     *
     * @since  4.1.0
     * @throws \Exception
     */
    public function nextExec(bool $string = true, bool $basisNow = false)
    {
        $executionRules = $this->getFromTask('execution_rules');
        $type           = $executionRules['rule-type'];
        switch ($type) {
            case 'interval-minutes':
                $now             = Factory::getDate('now', 'UTC');
                $intervalMinutes = (int) $executionRules['interval-minutes'];
                $interval        = new \DateInterval('PT' . $intervalMinutes . 'M');
                $nextExec        = $now->add($interval);
                $nextExec        = $string ? $nextExec->toSql() : $nextExec;
                break;
            case 'interval-hours':
                $now           = Factory::getDate('now', 'UTC');
                $intervalHours = $executionRules['interval-hours'];
                $interval      = new \DateInterval('PT' . $intervalHours . 'H');
                $nextExec      = $now->add($interval);
                $nextExec      = $string ? $nextExec->toSql() : $nextExec;
                break;
            case 'interval-days':
                $now             = Factory::getDate('now', 'UTC');
                $intervalDays    = $executionRules['interval-days'];
                $interval        = new \DateInterval('P' . $intervalDays . 'D');
                $nextExec        = $now->add($interval);
                $execTime        = $executionRules['exec-time'];
                [$hour, $minute] = explode(':', $execTime);
                $nextExec->setTime($hour, $minute);
                $nextExec = $string ? $nextExec->toSql() : $nextExec;
                break;
            case 'interval-months':
                $now            = Factory::getDate('now', 'UTC');
                $intervalMonths = $executionRules['interval-months'];
                $interval       = new \DateInterval('P' . $intervalMonths . 'M');
                $nextExec       = $now->add($interval);
                $execDay        = $executionRules['exec-day'];
                $nextExecYear   = $nextExec->format('Y');
                $nextExecMonth  = $nextExec->format('n');
                $nextExec->setDate($nextExecYear, $nextExecMonth, $execDay);

                $execTime        = $executionRules['exec-time'];
                [$hour, $minute] = explode(':', $execTime);
                $nextExec->setTime($hour, $minute);
                $nextExec = $string ? $nextExec->toSql() : $nextExec;
                break;
            case 'cron-expression':
                // @todo: testing
                $cExp     = new CronExpression((string) $this->rule->exp);
                $nextExec = $cExp->getNextRunDate('now', 0, false, Factory::getApplication()->get('offset', 'UTC'));
                $nextExec->setTimezone(new \DateTimeZone('UTC'));
                $nextExec = $string ? $this->dateTimeToSql($nextExec) : $nextExec;
                break;
            default:
                // 'manual' execution is handled here.
                $nextExec = null;
        }

        return $nextExec;
    }

    /**
     * Returns a sql-formatted string for a DateTime object.
     * Only needed for DateTime objects returned by CronExpression, JDate supports this as class method.
     *
     * @param   \DateTime  $dateTime  A DateTime object to format
     *
     * @return string
     *
     * @since  4.1.0
     */
    private function dateTimeToSql(\DateTime $dateTime): string
    {
        static $db;
        $db ??= Factory::getContainer()->get(DatabaseInterface::class);

        return $dateTime->format($db->getDateFormat());
    }
}
