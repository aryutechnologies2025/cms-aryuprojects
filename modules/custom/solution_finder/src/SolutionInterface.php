<?php

namespace Drupal\solution_finder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorage;

/**
 * Provides an interface defining a solution entity type.
 */
interface SolutionInterface extends ContentEntityInterface
{
    /**
     * @return Node[]
     */
    function getConcerns(): array;

    /**
     * @param Node[]|null $concerns
     * @return SolutionInterface
     */
    function setConcerns(?array $concerns): SolutionInterface;

    /**
     * @return array
     */
    function getConcernUuids(): array;

    /**
     * @return string
     */
    public function getSolutionPageUuid(): ?string;


    /**
     * @return Node|null
     */
    public function getSolutionPage(): ?Node;

    /**
     * @param Node|null $solution
     * @return SolutionInterface
     */
    public function setSolutionPage(?Node $solution): SolutionInterface;
}
