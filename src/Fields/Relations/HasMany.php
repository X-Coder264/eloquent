<?php
/*
 * Copyright 2021 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany as EloquentMorphMany;
use LaravelJsonApi\Eloquent\Contracts\FillableToMany;
use LaravelJsonApi\Eloquent\Fields\Concerns\ReadOnly;
use LogicException;
use function sprintf;

class HasMany extends ToMany implements FillableToMany
{

    private const KEEP_DETACHED_MODELS = 0;
    private const DELETE_DETACHED_MODELS = 1;
    private const FORCE_DELETE_DETACHED_MODELS = 2;

    use ReadOnly;

    /**
     * Flag for how to detach models from the relationship.
     *
     * @var int
     */
    private int $detachMode = self::KEEP_DETACHED_MODELS;

    /**
     * Create a has-many relation.
     *
     * @param string $fieldName
     * @param string|null $relation
     * @return HasMany
     */
    public static function make(string $fieldName, string $relation = null): HasMany
    {
        return new self($fieldName, $relation);
    }

    /**
     * Keep models that are detached by setting the inverse relationship column(s) to `null`.
     *
     * @return $this
     */
    public function keepDetachedModels(): self
    {
        $this->detachMode = self::KEEP_DETACHED_MODELS;

        return $this;
    }

    /**
     * Delete models that are detached using the `Model::delete()` method.
     *
     * @return $this
     */
    public function deleteDetachedModels(): self
    {
        $this->detachMode = self::DELETE_DETACHED_MODELS;

        return $this;
    }

    /**
     * Force delete models that are detached using the `Model::forceDelete()` method.
     *
     * @return $this
     */
    public function forceDeleteDetachedModels(): self
    {
        $this->detachMode = self::FORCE_DELETE_DETACHED_MODELS;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, array $identifiers): void
    {
        $this->sync($model, $identifiers);
    }

    /**
     * @inheritDoc
     */
    public function sync(Model $model, array $identifiers): iterable
    {
        $models = $this->findMany($identifiers);

        $this->doSync($model, $models);
        $model->setRelation($this->relationName(), $models);

        return $models;
    }

    /**
     * @inheritDoc
     */
    public function attach(Model $model, array $identifiers): iterable
    {
        $models = $this->findMany($identifiers);

        $this->getRelation($model)->saveMany($models);
        $model->unsetRelation($this->relationName());

        return $models;
    }

    /**
     * @inheritDoc
     */
    public function detach(Model $model, array $identifiers): iterable
    {
        $models = $this->findMany($identifiers);

        $this->doDetach($model, $models);
        $model->unsetRelation($this->relationName());

        return $models;
    }

    /**
     * @param Model $model
     * @param EloquentCollection $new
     */
    private function doSync(Model $model, EloquentCollection $new): void
    {
        $relation = $this->getRelation($model);
        $existing = $relation->get();

        $this->doDetach(
            $model,
            $existing->reject(fn($model) => $new->contains($model))
        );

        $relation->saveMany($new->reject(fn($model) => $existing->contains($model)));
    }

    /**
     * @param Model $model
     * @return EloquentHasMany|EloquentMorphMany
     */
    private function getRelation(Model $model)
    {
        $relation = $model->{$this->relationName()}();

        if ($relation instanceof EloquentHasMany || $relation instanceof EloquentMorphMany) {
            return $relation;
        }

        throw new LogicException(sprintf(
            'Expecting relation %s on model %s to be a has-many or morph-many relation.',
            $this->relationName(),
            get_class($model)
        ));
    }

    /**
     * Detach models from the relationship.
     *
     * @param Model $model
     * @param EloquentCollection $remove
     */
    private function doDetach(Model $model, EloquentCollection $remove): void
    {
        if (self::KEEP_DETACHED_MODELS === $this->detachMode) {
            $this->setInverseToNull($model, $remove);
            return;
        }

        $this->deleteRelatedModels($remove);
    }

    /**
     * Detach models by setting the inverse relation to `null`.
     *
     * @param Model $model
     * @param EloquentCollection $remove
     */
    private function setInverseToNull(Model $model, EloquentCollection $remove): void
    {
        $relation = $this->getRelation($model);

        /** @var Model $model */
        foreach ($remove as $model) {
            if ($relation instanceof EloquentMorphMany) {
                $model->setAttribute($relation->getMorphType(), null);
            }

            $model->setAttribute($relation->getForeignKeyName(), null)->save();
        }
    }

    /**
     * Detach models by deleting (or force deleting) the related models.
     *
     * @param EloquentCollection $remove
     */
    private function deleteRelatedModels(EloquentCollection $remove): void
    {
        /** @var Model $model */
        foreach ($remove as $model) {
            if (self::FORCE_DELETE_DETACHED_MODELS === $this->detachMode) {
                $model->forceDelete();
                continue;
            }

            $model->delete();
        }
    }

}
