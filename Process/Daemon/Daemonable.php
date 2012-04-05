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

use Siny\DaemonBundle\Process\ProcessInterface;

/**
 * This is an Daemonable interface
 *
 * @package SinyDaemonBundle
 * @subpackage daemon
 * @author Shinichiro Yuki <edy@siny.jp>
 */
interface Daemonable extends ProcessInterface
{
    /**
     * Set running directory
     *
     * @param SplFileInfo $dir
     * @return Siny\DaemonBundle\Process\Daemon\Daemonable
     * @throws InvalidArgumentException
     */
    public function setRunningDirectory(\SplFileInfo $dir);

    /**
     * Get running directory
     *
     * @return SplFileInfo
     */
    public function getRunningDirectory();

    /**
     * Is this daemon ?
     *
     * @return boolean - Whether this process is daemon
     */
    public function isDaemon();

    /**
     * Dispatch signal
     *
     * @return boolean
     */
    public function dispatchSignal();

    /**
     * Start daemon
     *
     * @throws Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function start();

    /**
     * Stop daemon
     *
     * @throws Siny\DaemonBundle\Process\Daemon\Exception\DaemonException
     */
    public function stop();
}
