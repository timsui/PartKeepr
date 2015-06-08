<?php
namespace PartKeepr\DoctrineReflectionBundle\Services;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Templating\EngineInterface;

class ReflectionService {

    /** @var EntityManager */
    protected $em;

    protected $templateEngine;

    protected $reader;

    public function __construct (Registry $doctrine, EngineInterface $templateEngine, Reader $reader) {
        $this->templateEngine = $templateEngine;
        $this->em = $doctrine->getManager();
        $this->reader = $reader;
    }

    /**
     * Returns a list of all registered entities, converted to the ExtJS naming scheme (. instead of \)
     * @return array
     */
    public function getEntities()
    {
        $entities = array();

        $meta = $this->em->getMetadataFactory()->getAllMetadata();

        foreach ($meta as $m) {
            /** @var ClassMetadata $m */
            $entities[] = $this->convertPHPToExtJSClassName($m->getName());
        }

        return $entities;
    }

    public function getEntity($entity)
    {
        $entity = $this->convertExtJSToPHPClassName($entity);

        $cm = $this->em->getClassMetadata($entity);

        $fields = $cm->getFieldNames();

        $fieldMappings = array();

        foreach ($fields as $field) {
            $currentMapping = $cm->getFieldMapping($field);

            $fieldMappings[] = array(
                "name" => $currentMapping["fieldName"],
                "type" => $this->getExtJSFieldMapping($currentMapping["type"]),
            );
        }

        $associations = $cm->getAssociationMappings();

        $associationMappings = array();

        foreach ($associations as $association) {
            $associationType = $association["type"];
            switch ($association["type"]) {
                case ClassMetadataInfo::MANY_TO_MANY:
                    $associationType = "MANY_TO_MANY";
                    break;
                //default:
//                    die("Unknown association ".$association["type"]);
            }

            $associationMappings[$associationType][] = array(
                "name" => $association["fieldName"],
                "target" => $this->convertPHPToExtJSClassName($association["targetEntity"]),
            );
        }

        $renderParams = array(
            "fields" => $fieldMappings,
            "associations" => $associationMappings,
            "className" => $this->convertPHPToExtJSClassName($entity),
        );

        $targetService = $this->reader->getClassAnnotation(
            $cm->getReflectionClass(),
            "PartKeepr\DoctrineReflectionBundle\Annotation\TargetService"
        );

        if ($targetService !== null) {
            $renderParams["uri"] = $targetService->uri;
        }

        return $this->templateEngine->render('PartKeeprDoctrineReflectionBundle::model.js.twig', $renderParams);
}

    protected function getExtJSFieldMapping($type)
    {
        switch ($type) {
            case "integer":
                return "int";
                break;
            case "string":
                return "string";
                break;
            case "text":
                return "string";
                break;
            case "datetime":
                return "date";
                break;
            case "boolean":
                return "boolean";
                break;
            case "float":
                return "number";
                break;
            case "decimal":
                return "number";
                break;
        }

        return "undefined";
    }

    protected function convertPHPToExtJSClassName($className)
    {
        return str_replace("\\", ".", $className);
    }

    protected function convertExtJSToPHPClassName($className)
    {
        return str_replace(".", "\\", $className);
    }
}