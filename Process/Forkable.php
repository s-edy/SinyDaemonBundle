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

/**
 * This is an forkable interface.
 *
 * @package SinyDaemonBundle
 * @subpackage process
 * @author Shinichiro Yuki <edy@siny.jp>
 */
interface Forkable
{
    /**
     * Fork
     *
     * @return integer - the PID of the created child process
     * @throws Siny\DaemonBundle\Process\Exception\ForkException
     */
    public function fork();

    /**
     * Is this process already forked ?
     *
     * @return boolean - Whether this process is already forked.
     */
    public function isForked();

    /**
     * Is this parent process ?
     *
     * @return boolean - Whether this is parent process.
     */
    public function isParentProcess();

    /**
     * Is this child process ?
     *
     * @return boolean - Whether this is parent process.
     */
    public function isChildProcess();
}
