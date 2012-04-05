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

use Siny\DaemonBundle\Process\Daemon\Worker\Workable;
use Siny\DaemonBundle\Process\Daemon\Worker\Exception\WorkerException;
use Monolog\Logger;

/**
 * This is a daemon class
 *
 * @package SinyDaemonBundle
 * @subpackage daemon
 * @author Shinichiro Yuki <edy@siny.jp>
 */
abstract class Worker implements Workable
{
    /**
     * Logger
     *
     * @var Monolog\Logger
     */
    private $logger;

    /**
     * Callbacks by signal
     *
     * @var array
     */
    private $callbacks = array();

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker.Workable::setLogger()
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker.Workable::getLogger()
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inhritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker.Workable::getRegistrationSignals()
     */
    public function getRegistrationSignals()
    {
        return array_keys($this->callbacks);
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker.Workable::getCallback()
     */
    public function getCallback($signal)
    {
        if ($this->hasCallback($signal)) {
            return $this->callbacks[$signal];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker.Workable::hasCallback()
     */
    public function hasCallback($signal)
    {
        return isset($this->callbacks[$signal]);
    }

    /**
     * Set callback
     *
     * @param integer $signal
     * @param mixed $callback
     */
    protected function setCallback($signal, $callback)
    {
        $this->callbacks[$signal] = $callback;

        return $this;
    }
}
