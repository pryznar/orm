<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Mapper\Dbal;

use Nette\Object;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\LogicException;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\InvalidArgumentException;
use Traversable;


/**
 * QueryBuilderHelper for Nextras\Dbal.
 */
class QueryBuilderHelper extends Object
{
	/** @var IModel */
	private $model;

	/** @var DbalMapper */
	private $mapper;

	/** @var MetadataStorage */
	private $metadataStorage;


	public function __construct(IModel $model, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->mapper = $mapper;
		$this->metadataStorage = $model->getMetadataStorage();
	}


	/**
	 * Transforms orm condition and adds it to QueryBuilder.
	 * @param  string       $expression
	 * @param  mixed        $value
	 * @param  QueryBuilder $builder
	 * @param  bool         $distinctNeeded
	 */
	public function processWhereExpression($expression, $value, QueryBuilder $builder, & $distinctNeeded)
	{
		list($chain, $operator) = ConditionParserHelper::parseCondition($expression);

		if ($value instanceof Traversable) {
			$value = iterator_to_array($value);
		}

		if (is_array($value) && count($value) === 0) {
			$builder->andWhere($operator === ConditionParserHelper::OPERATOR_EQUAL ? '1=0' : '1=1');
			return;
		}

		if ($operator === ConditionParserHelper::OPERATOR_EQUAL) {
			if (is_array($value)) {
				$operator = ' IN ';
			} elseif ($value === NULL) {
				$operator = ' IS ';
			} else {
				$operator = ' = ';
			}
		} elseif ($operator === ConditionParserHelper::OPERATOR_NOT_EQUAL) {
			if (is_array($value)) {
				$operator = ' NOT IN ';
			} elseif ($value === NULL) {
				$operator = ' IS NOT ';
			} else {
				$operator = ' != ';
			}
		} else {
			$operator = " $operator ";
		}

		$builder->andWhere(
			$this->normalizeAndAddJoins($chain, $this->mapper, $builder, $distinctNeeded)
			. $operator
			. '%any'
		, $value);
	}


	/**
	 * Transforms orm order by expression and adds it to QueryBuilder.
	 * @param  string       $expression
	 * @param  string       $direction
	 * @param  QueryBuilder $builder
	 */
	public function processOrderByExpression($expression, $direction, QueryBuilder $builder)
	{
		list($levels) = ConditionParserHelper::parseCondition($expression);
		$builder->addOrderBy(
			$this->normalizeAndAddJoins($levels, $this->mapper, $builder, $distinctNeeded)
			. ($direction === ICollection::DESC ? ' DESC' : '')
		);

		if ($distinctNeeded) {
			throw new LogicException("Cannot order by '$expression' expression, includes has many relationship.");
		}
	}


	private function normalizeAndAddJoins(array $levels, DbalMapper $sourceMapper, QueryBuilder $builder, & $distinctNeeded = FALSE)
	{
		$column = array_pop($levels);
		$entityMeta = $this->metadataStorage->get($sourceMapper->getRepository()->getEntityClassNames()[0]);

		$sourceAlias = $builder->getFromAlias();
		$sourceReflection = $sourceMapper->getStorageReflection();

		foreach ($levels as $level) {
			$property = $entityMeta->getProperty($level);
			if (!$property->relationshipRepository) {
				throw new InvalidArgumentException("Entity {$entityMeta->className}::\${$level} does not contain a relationship.");
			}

			$targetMapper     = $this->model->getRepository($property->relationshipRepository)->getMapper();
			$targetReflection = $targetMapper->getStorageReflection();

			if ($property->relationshipType === $property::RELATIONSHIP_ONE_HAS_MANY) {
				$targetColumn = $targetReflection->convertEntityToStorageKey($property->relationshipProperty);
				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];
				$distinctNeeded = TRUE;

			} elseif ($property->relationshipType === $property::RELATIONSHIP_MANY_HAS_MANY) {
				if ($property->relationshipIsMain) {
					list($joinTable, list($inColumn, $outColumn)) = $sourceMapper->getManyHasManyParameters($targetMapper);
				} else {
					list($joinTable, list($outColumn, $inColumn)) = $targetMapper->getManyHasManyParameters($sourceMapper);
				}

				$sourceColumn = $sourceReflection->getStoragePrimaryKey()[0];

				$builder->leftJoin(
					$sourceAlias,
					$joinTable,
					self::getAlias($joinTable),
					"[$sourceAlias.$sourceColumn] = [$joinTable.$inColumn]"
				);

				$sourceAlias = $joinTable;
				$sourceColumn = $outColumn;
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$distinctNeeded = TRUE;

			} else {
				$targetColumn = $targetReflection->getStoragePrimaryKey()[0];
				$sourceColumn = $sourceReflection->convertEntityToStorageKey($level);
			}

			$targetTable = $targetMapper->getTableName();
			$targetAlias = self::getAlias($targetTable);

			$builder->leftJoin(
				$sourceAlias,
				$targetTable,
				$targetAlias,
				"[$sourceAlias.$sourceColumn] = [$targetAlias.$targetColumn]"
			);

			$sourceAlias = $targetAlias;
			$sourceMapper = $targetMapper;
			$sourceReflection = $targetReflection;
			$entityMeta = $this->metadataStorage->get($sourceMapper->getRepository()->getEntityClassNames()[0]);
		}

		$entityMeta->getProperty($column); // check if property exists
		$column = $sourceReflection->convertEntityToStorageKey($column);
		return "{$sourceAlias}.{$column}";
	}


	public static function getAlias($name)
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}

}