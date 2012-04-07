<?php
/**
 * This file is a part of Siny\DaemonBundle package.
 *
 * (c) Shinichiro Yuki <edy@siny.jp>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siny\DaemonBundle\Process\Daemon\Worker;

use Siny\DaemonBundle\Process\Daemon\Worker\Exception\WorkerException;
use Monolog\Logger;

/**
 * This is an interface of worker.
 *
 * @author Shinichiro Yuki <edy@siny.jp>
 */
interface WorkableInterface
{
    /**
     * Set logger
     *
     * @param Logger $logger
     *
     * @return Siny\DaemonBundle\Process\Daemon\Worker\WorkerInterface
     */
    public function setLogger(Logger $logger);

    /**
     * Get logger
     *
     * @return Logger
     */
    public function getLogger();

    /**
     * Start
     *
     * @throws Siny\DaemonBundle\Worker\Exception\WorkerException
     */
    public function start();

    /**
     * Work
     *
     * @throws Siny\DaemonBundle\Worker\Exception\WorkerException
     */
    public function work();

    /**
     * Stop
     *
     * @param integer $signal The number of signal
     *
     * @throws Siny\DaemonBundle\Worker\Exception\WorkerException
     */
    public function stop($signal = null);

    /**
     * Get registration Signals
     *
     * @return array
     */
    public function getRegistrationSignals();

    /**
     * Get callback
     *
     * @param integer $signal The number of signal
     *
     * @return mixed Callback function or method
     *
     * @throws Siny\DaemonBundle\Worker\Exception\WorkerException
     */
    public function getCallback($signal);

    /**
     * Has callback
     *
     * @param integer $signal The number of signal
     *
     * @return boolean
     */
    public function hasCallback($signal);
}
