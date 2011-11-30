<?php

namespace Zenstruck\Bundle\ExcelBundle\Loader;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\Container;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ExcelLoader
{
    protected $container;
    protected $em;
    protected $repository;
    protected $class;
    protected $idField;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->em = $container->get('doctrine.orm.entity_manager');
    }

    public function load($filename, $shortClassName, $idField = null, $truncate = false)
    {

        if (!file_exists($filename)) {
            throw new \InvalidArgumentException('File does not exist');
        }

        try {
            list($bundleName, $entity) = explode(':', $shortClassName);

            $namespace = $this->container->get('kernel')->getBundle($bundleName)->getNamespace();
            $this->class = $namespace.'\\Entity\\'.$entity;

        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Entity name invalid.');
        }

        if (!class_exists($this->class)) {
            throw new \InvalidArgumentException('Entity does not exist.');
        }

        $this->idField = $idField;

        $this->repository = $this->em->getRepository($shortClassName);

        $excel = new \PHPExcel_Plus;
        $rows = $excel->load($filename)->convertToSimpleArray();

        if ($truncate) {
            $this->truncateTable();
        }

        $this->parseRows($rows);
    }

    protected function parseRows($rows)
    {
        // loop thru table rows
        foreach ($rows as $row) {

            $entity = new $this->class;

            // if id field is set, try and see if entity exists
            if ($this->idField) {
                $result = $this->repository->findOneBy(array($this->idField => $row[$this->idField]));

                if ($result) {
                    $entity = $result;
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
            $entity = $this->preSave($entity, $row);

            $this->em->persist($entity);
            $this->em->flush();

            // hook for custom code
            $this->postSave($entity, $row);
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
        $this->em->clear();
    }

    /**
     * PreSave Hook
     */
    protected function preSave($entity, $row)
    {
        return $entity;
    }

    /**
     * PostSave Hook
     */
    protected function postSave($entity, $row)
    {

    }
}
