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

use Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface;
use Siny\DaemonBundle\Process\Daemon\Worker\Exception\WorkerException;
use Monolog\Logger;

/**
 * This is a daemon class
 *
 * @author Shinichiro Yuki <edy@siny.jp>
 */
abstract class Worker implements WorkableInterface
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
     * @see Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface::setLogger()
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface::getLogger()
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * {@inhritdoc}
     *
     * @return array(integer)
     * @see Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface::getRegistrationSignals()
     */
    public function getRegistrationSignals()
    {
        return array_keys($this->callbacks);
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface::getCallback()
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
     * @see Siny\DaemonBundle\Process\Daemon\Worker\WorkableInterface::hasCallback()
     */
    public function hasCallback($signal)
    {
        return isset($this->callbacks[$signal]);
    }

    /**
     * Set callback
     *
     * @param integer $signal   The number of signal
     * @param mixed   $callback Callback function or method
     *
     * @return self
     */
    protected function setCallback($signal, $callback)
    {
        $this->callbacks[$signal] = $callback;

        return $this;
    }
}
