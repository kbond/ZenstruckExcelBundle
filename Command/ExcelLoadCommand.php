<?php

namespace Zenstruck\Bundle\ExcelBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ExcelLoadCommand extends ContainerAwareCommand
{
    protected $em;
    protected $repository;
    protected $class;
    protected $idField;

    protected function configure()
    {
        $this
                ->setName('zenstruck:excel:load')
                ->setDescription('Import an excel table into your database')
                ->addArgument('entity', InputArgument::REQUIRED, 'The entity shortname - ie ApplicationBundle:Tag')
                ->addArgument('file', InputArgument::REQUIRED, 'The excel file to import')
                ->addOption('id', null, null, 'Field to use as identifier for updating data')
                ->addOption('truncate', null, null, 'Whether to truncate the table before import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $provider = $this->getContainer()->get('zenstruck_excel.provider');

        $provider->load($input->getArgument('file'), $input->getArgument('entity'), $input->getOption('id'), $input->getOption('truncate'));
    }
}
