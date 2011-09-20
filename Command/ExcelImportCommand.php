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
class ExcelImportCommand extends ContainerAwareCommand
{
    protected $em;
    protected $repository;
    protected $class;
    protected $idField;

    protected function configure()
    {
        $this
                ->setName('zenstruck:excel:import')
                ->setDescription('Import an excel table')
                ->addArgument('entity', InputArgument::REQUIRED, 'The entity shortname - ie ApplicationBundle:Tag')
                ->addArgument('file', InputArgument::REQUIRED, 'The excel file to import')
                ->addOption('id', null, null, 'Field to use as identifier for updating data')
                ->addOption('truncate', null, null, 'Whether to truncate the table before import')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $shortClassName = $input->getArgument('entity');

        try {
            list($bundleName, $entity) = explode(':', $shortClassName);

            $namespace = $this->getContainer()->get('kernel')->getBundle($bundleName)->getNamespace();
            $this->class = $namespace.'\\Entity\\'.$entity;

        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Entity name invalid.');
        }

        if (!class_exists($this->class)) {
            throw new \InvalidArgumentException('Entity does not exist.');
        }

        $this->idField = $input->getOption('id');

        // hook for custom code
        $file = $this->getFile($input);

        if (!file_exists($file)) {
            throw new \InvalidArgumentException('File not found.');
        }

        /* @var $em \Doctrine\ORM\EntityManager */
        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = $this->em->getRepository($shortClassName);

        if ($input->getOption('truncate')) {
            $output->writeln('Truncating table');
            $this->truncateTable();
        }

        $excel = new \PHPExcel_Plus;
        $rows = $excel->load($file)->convertToSimpleArray();

        $this->parseRows($rows, $output);
    }

    protected function parseRows($rows, OutputInterface $output)
    {
        // loop thru table rows
        foreach ($rows as $row) {
            $action = 'Added';

            $entity = new $this->class;

            // if id field is set, try and see if entity exists
            if ($this->idField) {
                $result = $this->repository->findOneBy(array($this->idField => $row[$this->idField]));

                if ($result) {
                    $entity = $result;

                    $action = 'Updated';
                }
            }

            // loop through all cells in row, see if method to set exists
            foreach ($row as $key => $value) {

                if (!$value) {
                    continue;
                }

                // check for foregn ref
                try {
                    list($field, $foreignId, $foreignEntity) = explode('|', $key);

                    if (method_exists($entity, $this->getEntityMethodName($field, 'add'))) {
                        // many to many field
                        $entity = $this->setManyToManyProperty($entity, $field, $foreignId, $foreignEntity, $value);
                    } else {
                        // one to many field
                        $entity = $this->setOneToManyProperty($entity, $field, $foreignId, $foreignEntity, $value);
                    }
                } catch (\Exception $exc) {
                    // standard property
                    $entity = $this->setStandardProperty($entity, $key, $value);
                }
            }

            // hook for custom code
            $entity = $this->customEntityLogic($entity, $row);



            $this->em->persist($entity);
            $this->em->flush();

            $output->write('.');
        }
    }

    protected function setManyToManyProperty($entity, $field, $foreignId, $foreignEntity, $value)
    {
        return $entity;
    }

    protected function setOneToManyProperty($entity, $field, $foreignId, $foreignEntity, $value)
    {
        $foreignEntity = $this->em->getRepository($foreignEntity)->findOneBy(array($foreignId => $value));

        if ($foreignEntity) {
            $this->callEntityMethod($entity, $field, $foreignEntity);
        }

        return $entity;
    }

    protected function setStandardProperty($entity, $field, $value)
    {
        return $this->callEntityMethod($entity, $field, $value);
    }

    protected function getEntityMethodName($field, $prefix = 'set')
    {
        return $prefix . ucfirst($field);
    }

    protected function callEntityMethod($entity, $field, $value, $prefix = 'set')
    {
        $methodName = $this->getEntityMethodName($field, $prefix);

        if (method_exists($entity, $methodName)) {
            $entity->$methodName($value);
        }

        return $entity;
    }

    protected function truncateTable()
    {
        foreach ($this->repository->findAll() as $entity) {
            $this->em->remove($entity);
        }

        $this->em->flush();
    }

    protected function performAdditionalLogic($entity, $row)
    {
        return $entity;
    }

    /**
     * Override to add custom logic
     */
    public function customEntityLogic($entity, $row)
    {
        return $entity;
    }

    /**
     * Override to add custom getFile logic
     *
     * @param InputInterface $input
     */
    protected function getFile(InputInterface $input)
    {
        return $input->getArgument('file');
    }
}
