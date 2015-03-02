<?php

namespace Riverline\LoopCommand\Command;

use Riverline\LoopCommand\Context\ArrayContext;
use Riverline\LoopCommand\Context\LoopCommandContextInterface;
use Riverline\LoopCommand\Exception\LoopCommandFinishedException;
use \Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use \Symfony\Component\DependencyInjection\ContainerAwareInterface;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AbstractLoopCommand
 * @package Riverline\LoopCommand\Command
 */
abstract class AbstractLoopCommand extends Command
{
    const COMMAND_VALUE_REQUIRED = 'r';
    const COMMAND_VALUE_OPTIONAL = 'o';
    const COMMAND_VALUE_NONE     = 'n';

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var DialogHelper
     */
    private $dialog;

    /**
     * @var LoopCommandContextInterface
     */
    private $context;

    /**
     * @var array
     */
    protected $commands = array();

    final protected function configure()
    {
        $this
            ->addCommand('quit', array($this, 'doQuit'), self::COMMAND_VALUE_NONE, 'Exits the utility')
            ->addCommand('help', array($this, 'doHelp'), self::COMMAND_VALUE_NONE, 'Displays help on available commands');

        $this->configureLoop();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var FormatterHelper $formatterHelper */
        $formatterHelper = $this->getHelperSet()->get('formatter');
        $that = $this;

        // Main exception catching to allow quiting command
        try {
            while (true) {
                // sub exception catching to display error without giving up context changes
                try {
                    // Do not display available commands
                    $commandToExecute = $this->dialog->askAndValidate(
                        $output,
                        'loop > ',
                        function ($answer) use ($that) {
                            // command accepts arguments, lets explode it and keep the trailing args in a simple string
                            $allArgs = explode(' ', $answer, 2);
                            if (empty($allArgs[0])) {
                                return array();
                            } elseif (array_key_exists($allArgs[0], $this->commands)) {
                                $command = array_shift($allArgs);
                                $arguments = (!empty($allArgs) ? $allArgs[0] : null);

                                // check if args are required, optional, or shouldn't exist
                                if (empty($arguments) && $this->commands[$command]['mode'] == self::COMMAND_VALUE_REQUIRED) {
                                    throw new \RuntimeException('The command "'.$command.'" needs one or many arguments, nothing given.');
                                } elseif (!empty($arguments) && $this->commands[$command]['mode'] == self::COMMAND_VALUE_NONE) {
                                    throw new \RuntimeException('The command "'.$command.'" doesn\'t accept arguments, "'.$arguments.'" given.');
                                }

                                return array(
                                    'command'   => $command,
                                    'arguments' => $arguments
                                );
                            } else {
                                throw new \RuntimeException('Unknown command');
                            }
                        },
                        false,
                        null,
                        array_keys($that->commands)
                    );

                    if (!empty($commandToExecute)) {
                        call_user_func_array(
                            $this->commands[$commandToExecute['command']]['callable'],
                            array($input, $output, $this->dialog, $this->context, $commandToExecute['arguments'])
                        );
                    }
                } catch (LoopCommandFinishedException $lcfe) {
                    throw $lcfe;
                } catch (\Exception $e) {
                    $output->writeln($formatterHelper->formatBlock('Exception ' . get_class($e) . ': ' . $e->getMessage(), 'error', true));
                }
            }
        } catch (LoopCommandFinishedException $lcfe) {
            // Nothing to do but return with a message
            $output->writeln('<comment>Bye</comment>');

            return 0;
        }

        // we shouldn't be able to get there
        return 10;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input        = $input;
        $this->output       = $output;
        $this->dialog       = $this->getHelperSet()->get('dialog');
        $this->context      = $this->getNewContext();

        $this->initializeLoop($input, $output, $this->dialog, $this->context);
    }

    /**
     * Should be overloaded in implementation
     *
     * @return LoopCommandContextInterface
     */
    protected function getNewContext ()
    {
        return new ArrayContext();
    }

    /**
     * @return mixed
     */
    abstract protected function configureLoop();

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param DialogHelper $dialog
     * @param LoopCommandContextInterface $context
     */
    abstract protected function initializeLoop(InputInterface $input, OutputInterface $output, DialogHelper $dialog, LoopCommandContextInterface $context);

    /**
     * @param string $name
     * @param array  $callable
     * @param string $mode
     * @param string $description
     * @return AbstractLoopCommand
     */
    public function addCommand($name, $callable, $mode = null, $description = '')
    {
        $this->commands[$name] = array(
            'description'   => $description,
            'callable'      => $callable,
            'mode'          => $mode,
        );

        return $this;
    }

    /**
     * @param InputInterface              $input
     * @param OutputInterface             $output
     * @param DialogHelper                $dialog
     * @param LoopCommandContextInterface $context
     */
    protected function doHelp (InputInterface $input, OutputInterface $output, DialogHelper $dialog, LoopCommandContextInterface $context)
    {
        $tabLength = 8;
        $maxTabs = 3;
        // display all commands description
        $output->writeln('<comment>Available commands (extended):</comment>');
        foreach ($this->commands as $command => $details) {
            $trailingTabs = ceil((($tabLength * $maxTabs) - (strlen($command) + 3)) / $tabLength);
            $output->writeln("  <info>".$command.":</info>".str_repeat("\t", $trailingTabs).$details['description']);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param DialogHelper $dialog
     * @param LoopCommandContextInterface $context
     * @throws LoopCommandFinishedException
     */
    protected function doQuit(InputInterface $input, OutputInterface $output, DialogHelper $dialog, LoopCommandContextInterface $context)
    {
        throw new LoopCommandFinishedException();
    }

}
