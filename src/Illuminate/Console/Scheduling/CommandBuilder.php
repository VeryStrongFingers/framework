<?php

namespace Illuminate\Console\Scheduling;

use Illuminate\Console\Application;
use Illuminate\Support\ProcessUtils;

class CommandBuilder
{
    /**
     * Build the command for the given event.
     *
     * Possible conditions:
     *  [1] windows - background
     *  [2] windows - foreground
     *  [3] not windows - background, with user
     *  [4] not windows - background, without user
     *  [5] not windows - foreground, with user
     *  [6] not windows - foreground, without user
     *
     * @param  \Illuminate\Console\Scheduling\Event  $event
     * @return string
     */
    public function buildCommand(Event $event)
    {
        $redirect = $event->shouldAppendOutput ? '>>' : '>';

        $primaryCommand = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg($event->command),
            $redirect,
            escapeshellarg($event->output)
        );

        // covering ALL foreground cases
        if ($event->runInBackground === false) {
            if (is_string($event->user) === true && windows_os() === false) {
                // format command to run through sudo
                $runAsUser = sprintf('sudo -u %s -- sh -c', escapeshellarg($event->user));

                return sprintf('%s \'%s\'', $runAsUser, $primaryCommand);
            }

            return $primaryCommand;
        }

        $finished = sprintf(
            '%s %s',
            Application::formatCommandString('schedule:finish'),
            escapeshellarg($event->mutexName())
        );

        // covers case 1
        if (windows_os() === true) {
            return sprintf(
                'start /b cmd /c "(%s & %s "%%errorlevel%%") %s %s 2>&1"',
                escapeshellarg($event->command),
                $finished,
                $redirect,
                escapeshellarg($event->output)
            );
        }

        $primaryCommand = sprintf(
            '(%s; %s $?) > %s 2>&1 &',
            $primaryCommand,
            $finished,
            escapeshellarg($event->getDefaultOutput())
        );

        if (is_string($event->user) === true) {
            $runAsUser = sprintf('sudo -u %s -- sh -c', escapeshellarg($event->user));

            return sprintf('%s \'%s\'', $runAsUser, $primaryCommand);
        }

        return $primaryCommand;
    }
}
