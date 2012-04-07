<?php
/**
 * This file is a part of Siny\DaemonBundle package.
 *
 * (c) Shinichiro Yuki <edy@siny.jp>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Siny\DaemonBundle\Process;

use Siny\DaemonBundle\Process\ProcessInterface;
use Siny\DaemonBundle\Process\Exception\ForkException;
use Siny\DaemonBundle\Process\Exception\WaitException;
use Siny\DaemonBundle\Process\Exception\ProcessException;

/**
 * This is a process class
 *
 * @author Shinichiro Yuki <edy@siny.jp>
 */
class Process implements ProcessInterface
{
    /**
     * Handling signals
     *
     * @var array
     */
    protected $handlingSignals = array();

    /**
     * Is this process parent ?
     *
     * @var boolean
     */
    protected $isParent = true;

    /**
     * Is this process child ?
     *
     * @var boolean
     */
    protected $isChild = false;

    /**
     * Catched signal
     * @var integer
     */
    private $caughtSignal = 0;

    /**
     * Is this process parent ?
     *
     * @var boolean
     */
    private $isForked = false;

    /**
     * Wait status
     *
     * @var integer
     */
    private $waitStatus;

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process.ProcessInterface::registerSignal()
     *
     * @throws Siny\DaemonBundle\Process\Exception\ProcessException
     */
    public function registerSignal($signal, $callback)
    {
        try {
            $callback = $this->normalizeCallback($callback);
            if (! $this->doSignal($signal, array($this, "handleSignal"))) {
                throw new \InvalidArgumentException("Failed to register signal");
            }
            if ($callback !== SIG_IGN && $callback !== SIG_DFL) {
                $this->handlingSignals[$signal] = $callback;
            }
        } catch (\Exception $e) {
            if (is_array($callback)) {
                $callback = get_class($callback[0]) . '::' . $callback[1];
            }
            throw new ProcessException(sprintf("Failed to register signal. signal=[%s], callback=[%s]", $signal, $callback), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process.ProcessInterface::handleSignal()
     */
    public function handleSignal($signal)
    {
        // TODO: make test
        //$this->caughtSignal = $signal;
        call_user_func($this->handlingSignals[$signal], $signal);
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process.ProcessInterface::getRegisteredSignals()
     */
    public function getRegisteredSignals()
    {
        return $this->handlingSignals;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\ForkableInterface::fork()
     */
    public function fork()
    {
        $pid = $this->doFork();
        if ($pid === -1) {
            throw new ForkException("Failed to fork.");
        }
        $this->isForked = true;
        $this->isChild = ($pid === 0);
        $this->isParent = ($pid > 0);

        return $pid;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process\WaitableInterface::wait()
     */
    public function waitPID($pid = -1, $option = 0)
    {
        $pid = $this->doWaitPID($pid, $option);
        if ($pid === -1) {
            throw new WaitException("Failed to wait.");
        }

        return $pid;
    }

    /**
     * Set SID
     *
     * @return integer session id
     * @throws \RuntimeException
     */
    public function setSid()
    {
        $sessionId = $this->doSetSid();
        if ($sessionId === -1) {
            throw new ProcessException(sprintf("Failed to set SID. PID=[%s], Process Group ID=[%s]", posix_getpid(), posix_getpgrp()));
        }

        return $sessionId;
    }

    /**
     * Change current directory
     *
     * @param SplFileInfo $directory
     *
     * @throws \RuntimeException
     */
    public function chdir(\SplFileInfo $directory)
    {
        if (! $directory->isDir()) {
            throw new ProcessException(sprintf("Failed to change directory. [%s] is not directory.", $directory->getRealPath()));
        }
        if (! $directory->isReadable()) {
            throw new ProcessException(sprintf("Failed to change directory. [%s] is not readable.", $directory->getRealPath()));
        }
        if (! $this->doChdir($directory->getRealPath())) {
            throw new ProcessException(sprintf("Failed to change directory. expects=[%s], actual=[%s]", $directory->getRealPath(), getcwd()));
        }
    }

    /**
     * Changes umask
     *
     * @param integer $expects Umask octal number
     *
     * @return number Old umask
     * @throws \RuntimeException
     */
    public function umask($expects = null)
    {
        $before = $this->doUmask($expects);
        if (! is_null($expects) && $before === $this->doUmask()) {
            throw new ProcessException(sprintf("Failed to change umask. expect=[%s], before=[%s]", $expects, $before));
        }

        return $before;
    }

    /**
     * Closes an open file pointer
     *
     * This method is being just only wrapped fclose() fiunction.
     *
     * @param resource $descriptor
     *
     * @throws \RuntimeException
     */
    public function fclose($descriptor)
    {
        if (! $this->doFclose($descriptor)) {
            throw new ProcessException(sprintf("Failed to close. descriptor=[%s]", var_export($descriptor, true)));
        }
    }

    /**
     * {@inheritdoc}
	 *
     * @see Siny\DaemonBundle\Process\ForkableInterface::isForked()
     */
    public function isForked()
    {
        return $this->isForked;
    }

    /**
     * {@inheritdoc}
	 *
     * @see Siny\DaemonBundle\Process\ForkableInterface::isParentProcess()
     */
    public function isParentProcess()
    {
        return $this->isParent;
    }

    /**
     * {@inheritdoc}
	 *
     * @see Siny\DaemonBundle\Process\ForkableInterface::isChildProcess()
     */
    public function isChildProcess()
    {
        return $this->isChild;
    }

    /**
     * {@inheritdoc}
     *
     * @see Siny\DaemonBundle\Process.ProcessInterface::catchesSignal()
     */
    public function catchesSignal()
    {
        return $this->caughtSignal > 0;
    }

    /**
     * Register signal handler
     *
     * @param integer $signal   The signal number
     * @param mixed   $callback Callback function, or method.
     *
     * @return Returns TRUE on success or FALSE on failure.
     * @see {@link http://www.php.net/manual/en/function.pcntl-signal.php}
     */
    protected function doSignal($signal, $callback)
    {
        return pcntl_signal($signal, $callback);
    }

    /**
     * Fork actually
     *
     * @see {@link http://www.php.net/manual/en/function.pcntl-fork.php}
     *
     * @return integer
     */
    protected function doFork()
    {
        return pcntl_fork();
    }

    /**
     * Wait actually
     *
     * @param intger  $pid    The value of pid
     * @param integer $option 0, WNOHANG, WUNTRACED
     *
     * @return Same value as pcntl_waitpid returns value
     * @see {@link http://www.php.net/manual/en/function.pcntl-wait.php}
     */
    protected function doWaitPID($pid = -1, $option = 0)
    {
        return pcntl_waitpid($pid, $this->waitStatus, $option);
    }

    /**
     * Set sid
     *
     * @return integer session id
     */
    protected function doSetSid()
    {
        return posix_setsid();
    }

    /**
     * Chdir
     *
     * @param string $dir The new current directory
     *
     * @return boolean
     */
    protected function doChdir($dir)
    {
        return chdir($dir);
    }

    /**
     * umask
     *
     * @param integer $expects Umask octal number
     *
     * @return integer
     */
    protected function doUmask($expects = null)
    {
        if (is_null($expects)) {
            return umask();
        } else {
            return umask($expects);
        }
    }

    /**
     * fclose
     *
     * @param resource $descriptor Opend file pointer
     *
     * @return boolean
     */
    protected function doFclose($descriptor)
    {
        return fclose($descriptor);
    }

    /**
     * Normalize callback
     *
     * @param mixed $callback SIG_IGN | SIG_DFL, or callback function, or callback object array.
     *
     * @return mixed Callback function or method.
     * @throws \InvalidArgumentException
     */
    private function normalizeCallback($callback)
    {
        if ($callback === SIG_IGN || $callback === SIG_DFL) {
            return $callback;
        }
        if (! is_callable($callback, false)) {
            throw new \InvalidArgumentException("Callback variable wasn't callable.");
        }

        return $callback;
    }
}
