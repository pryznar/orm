<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Relationships;

use Nextras\Orm\Entity\IEntity;


class OneHasMany extends HasMany
{
	public function getEntitiesForPersistance()
	{
		$entities = [];
		foreach ($this->toAdd as $add) {
			$entities[] = $add;
		}
		foreach ($this->toRemove as $remove) {
			if ($remove->isPersisted()) {
				$entities[] = $remove;
			}
		}
		if ($this->collection !== NULL) {
			foreach ($this->getIterator() as $entity) {
				$entities[] = $entity;
			}
		}
		return $entities;
	}


	public function doPersist()
	{
		$this->toAdd = [];
		$this->toRemove = [];
		$this->collection = NULL;
		$this->isModified = FALSE;
	}


	protected function modify()
	{
		$this->isModified = TRUE;
	}


	protected function createCollection()
	{
		$collection = $this->getTargetRepository()->getMapper()->createCollectionOneHasMany($this->metadata, $this->parent);
		return $this->applyDefaultOrder($collection);
	}


	protected function updateRelationshipAdd(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue($this->parent);
		$this->updatingReverseRelationship = FALSE;
	}


	protected function updateRelationshipRemove(IEntity $entity)
	{
		$this->updatingReverseRelationship = TRUE;
		$entity->getProperty($this->metadata->relationship->property)->setInjectedValue(NULL);
		$this->updatingReverseRelationship = FALSE;
	}
}
