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

namespace LaravelJsonApi\Eloquent;

use IteratorAggregate;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;

class EagerLoadMorphs implements IteratorAggregate
{

    /**
     * @var Container
     */
    private Container $schemas;

    /**
     * @var MorphTo
     */
    private MorphTo $relation;

    /**
     * @var IncludePaths
     */
    private IncludePaths $paths;

    /**
     * EagerLoadMorphPath constructor.
     *
     * @param Container $schemas
     * @param MorphTo $relation
     * @param IncludePaths $paths
     */
    public function __construct(Container $schemas, MorphTo $relation, IncludePaths $paths)
    {
        $this->schemas = $schemas;
        $this->relation = $relation;
        $this->paths = $paths;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->relation->relationName();
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return array_filter(iterator_to_array($this));
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        foreach ($this->relation->inverseTypes() as $type) {
            $schema = $this->schemas->schemaFor($type);
            $loader = new EagerLoader($this->schemas, $schema);

            yield $schema->model() => $loader->skipMissingFields()->toRelations($this->paths);
        }
    }

}
