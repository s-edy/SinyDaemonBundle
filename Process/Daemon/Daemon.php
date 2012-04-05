<?php
/**
 * This file is a part of Siny\DaemonBundle package.
 *
 * (c) Shinichiro Yuki <edy@siny.jp>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siny\DaemonBundle\Process\Daemon;

use Siny\DaemonBundle\Process\Process;
use Siny\DaemonBundle\Process\Daemon\Daemonable;
use Siny\DaemonBundle\Process\Daemon\Worker\Workable;
use Siny\DaemonBundle\Process\Daemon\Exception\DaemonException;

/**
 * This is a daemon class
 *
 * @package SinyDaemonBundle
 * @subpackage daemon
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Daemon extends Process implements Daemonable
{
    /**
     * Default running directory
     *
     * @var string
     */
    const DEFAULT_RUNNING_DIRECTORY = DIRECTORY_SEPARATOR;

    /**
     * Workable class
     *
     * @var Siny\DaemonBundle\Process\Daemon\Worker\Workable
     */
    private $worker;

    /**
     * Is this process daemon ?
     *
     * @var boolean
     */
    private $isDaemon = false;

    /**
     * Running directory
     *
     * @var SplFileInfo
     */
    private $runningDirectory;

    /**
     * Set Options when construction
     *
     * @param Workable $worker
     */
    public function __construct(Workable $worker)
    {
        $this->worker = $worker;
        foreach ($this->getWorker()->getRegistrationSignals() as $signal) {
            if ($this->getWorker()->hasCallback($signal)) {
                $this->registerSignal($signal, $this->getWorker()->getCallback($signal));
            }
        }
        $this->setRunningDirectory(new \SplFileInfo(self::DEFAULT_RUNNING_DIRECTORY));
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::setRunningDirectory()
     */
    public function setRunningDirectory(\SplFileInfo $dir)
    {
        if (! $dir->isDir()) {
            throw new \InvalidArgumentException(sprintf("Specified running directory wasn't directory. dir=[%s]", $dir->getRealPath()));
        }
        if (! $dir->isReadable()) {
            throw new \InvalidArgumentException(sprintf("Specified running directory wasn't readable. dir=[%s]", $dir->getRealPath()));
        }
        $this->runningDirectory = $dir;

        return $this;
    }

    /**
     * Get worker
     *
     * @return Siny\DaemonBundle\Process\Daemon\Worker\Workable
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::getRunningDirectory()
     */
    public function getRunningDirectory()
    {
        return $this->runningDirectory;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::isDaemon()
     */
    public function isDaemon()
    {
        return $this->isDaemon;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::dispatchSignal()
     */
    public function dispatchSignal()
    {
        return pcntl_signal_dispatch();
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::run()
     */
    public function start()
    {
        $this->daemonize();
        if (! $this->isDaemon()) {
            return;
        }
        $this->getWorker()->start();
        $this->run();
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\Daemon.Daemonable::stop()
     */
    public function stop()
    {
        $this->getWorker()->stop();
    }

    /**
     * Process to be a daemon
     *
     *  1. double-fork: 1st fork, in order to separate a controlling terminal.
     *                  2nd fork, in order to avoid matching any controlling terminal.
     *  2. changing current directory to running directory in order to avoid locking directory
     *  3. changing umask to zero in order to set permission correctly as creating file or directory.
     *  4. closing every file descriptor
     */
    public function daemonize()
    {
        $this->isDaemon = false;
        try {
            $this->fork();
            if ($this->isParentProcess()) {
                return;
            }
            $this->setSid();
            $this->fork();
            if ($this->isParentProcess()) {
                $this->isParent = false;
                $this->isChild = true;
                return;
            }
            $this->isChild = false;
            $this->chdir($this->getRunningDirectory());
            $this->umask(0);
            $this->fclose(STDIN);
            $this->fclose(STDOUT);
            $this->fclose(STDERR);
            $this->isDaemon = true;
        } catch (\Exception $e) {
            throw new DaemonException(sprintf("Failed to daemonize"), 0, $e);
        }
    }

    /**
     * Running infinately until catching any signal
     */
    public function run()
    {
        $worker = $this->getWorker();
        while (! $this->catchesSignal()) {
            $worker->work();
            $this->dispatchSignal();
        }
    }
}
