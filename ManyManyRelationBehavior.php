<?php

namespace omnilight\behaviors;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;


/**
 * Class ManyManyRelationBehavior
 */
class ManyManyRelationBehavior extends Behavior
{
    /**
     * Name of the many to many relation of the model (ex.: books)
     * @var string
     */
    public $relationName;
    /**
     * Name of the generated property that will be used to represent ids of the related tables (ex.: bookIds)
     * @var string
     */
    public $propertyName;
    /**
     * @var array
     */
    protected $_relationIds;

    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function canGetProperty($name, $checkVars = true)
    {
        if ($name == $this->propertyName)
            return true;
        return parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)
    {
        if ($name == $this->propertyName)
            return true;
        return parent::canSetProperty($name, $checkVars);
    }

    public function __get($name)
    {
        if ($name == $this->propertyName)
            return $this->getRelationIds();
        else
            return parent::__get($name);
    }

    public function __set($name, $value)
    {
        if ($name == $this->propertyName)
            $this->setRelationIds($value);
        else
            parent::__set($name, $value);
    }


    public function getRelationIds()
    {
        if (!$this->owner->getIsNewRecord() && $this->_relationIds === null) {
            $this->populateRelationIds();
        }

        return $this->_relationIds === null ? [] : $this->_relationIds;
    }

    public function setRelationIds($relationIds)
    {
        $this->_relationIds = (array)$relationIds;
    }

    protected function populateRelationIds()
    {
        $this->_relationIds = [];
        foreach ($this->owner->{$this->relationName} as $region) {
            /** @var ActiveRecord $region */
            $this->_relationIds[] = $region->getPrimaryKey();
        }
    }

    public function afterSave()
    {
        if ($this->_relationIds === null) {
            return;
        }

        $this->beforeDelete();

        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        $relation = $owner->getRelation($this->relationName);
        $pivot = $relation->via->from[0];
        $rows = [];

        foreach ($this->_relationIds as $relatedId) {
            if (!empty($relatedId)) {
                $rows[] = [$owner->getPrimaryKey(), $relatedId];
            }
        }

        if (!empty($rows)) {
            $owner->getDb()
                ->createCommand()
                ->batchInsert($pivot, [key($relation->via->link), current($relation->link)], $rows)
                ->execute();
        }
    }

    /**
     * @return void
     */
    public function beforeDelete()
    {
        /** @var ActiveRecord $owner */
        $owner = $this->owner;
        $relation = $owner->getRelation($this->relationName);
        $pivot = $relation->via->from[0];
        $owner->getDb()
            ->createCommand()
            ->delete($pivot, [key($relation->via->link) => $owner->getPrimaryKey()])
            ->execute();
    }
}