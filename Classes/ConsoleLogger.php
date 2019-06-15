<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot;

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Simple CLI logger that uses a symfony output to write its data to
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class ConsoleLogger extends AbstractLogger
{
    /**
     * @var OutputInterface
     */
    private $output;

    private $jsonOptions =
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_LINE_TERMINATORS |
        JSON_UNESCAPED_UNICODE |
        JSON_PRETTY_PRINT;

    /**
     * ConsoleLogger constructor.
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function log($level, $message, array $context = [])
    {
        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::CRITICAL:
            case LogLevel::ALERT:
                $verbosity = OutputInterface::VERBOSITY_QUIET;
                break;

            case LogLevel::ERROR:
                $verbosity = OutputInterface::VERBOSITY_NORMAL;
                break;

            case LogLevel::WARNING:
            case LogLevel::NOTICE:
                $verbosity = OutputInterface::VERBOSITY_VERBOSE;
                break;

            default:
                $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE;
        }

        $this->output->writeln($message, $verbosity);

        if ($context) {
            $this->output->writeln(json_encode($context, $this->jsonOptions), $verbosity);
        }
    }
}
