<?php

namespace Drupal\migrate_source_graphql\GraphQL;

use GraphQL\Client as GraphQLClient;
use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\QueryBuilder\QueryBuilderInterface;

/**
 * Class Client that implements some useful methods to interact with GraphQL.
 */
class Client {

  /**
   * The client.
   *
   * @var \GraphQL\Client
   */
  protected $client;

  /**
   * The query builder.
   *
   * @var \GraphQL\QueryBuilder
   */
  private $queryBuilder;

  /**
   * Client constructor.
   *
   * @param string $apiEndpoint
   *   API Endpoint.
   * @param array $extraHeader
   *   Extra headers.
   *
   * @return \GraphQL\Client
   */
  public function __construct(string $apiEndpoint, array $extraHeader) {
    $this->client = new GraphQLClient(
      $apiEndpoint,
      $extraHeader
    );

    return $this->client;
  }

  /**
   * Build the query.
   *
   * @param string $queryName
   *   The query's name.
   * @param array $selectionSet
   *   Stores the selection set desired to get from the query.
   * @param mixed|null $arguments
   *   Query arguments.
   * @param mixed|null $filters
   *   Query filters.
   *
   * @return \GraphQL\Query
   *   The created query.
   */
  public function buildQuery(string $queryName, array $selectionSet, mixed $arguments = NULL, mixed $filters = NULL) {
    $this->arrayToQuery($selectionSet);

    $query = (new Query($queryName))
      ->setSelectionSet($selectionSet);

    if ($arguments !== NULL) {
      $argumentsKey = array_key_first($arguments);
      $argumentsToString = json_encode($arguments[$argumentsKey]);
      $argumentsToString = preg_replace("/['\"]/", '', $argumentsToString);

      $arguments = [
        $argumentsKey => new RawObject($argumentsToString),
      ];

      if ($filters !== NULL) {
        $arguments['filters'] = $filters;
      }

      $query->setArguments($arguments);
    }

    return $query;
  }

  /**
   * Run query.
   *
   * @param Query $query
   *   Created query to use.
   * @return \GraphQL\Results
   */
  public function runQuery(\GraphQL\Query $query) {
    return $this->client->runQuery($query);
  }

  /**
   * Recursive array to Query object transform to build a right selectionSet.
   *
   * @param array $array
   *   Recursive array to Query transform to build a right selectionSet.
   */
  private function arrayToQuery(array &$array) {
    foreach ($array as &$item) {
      if (is_array($item)) {
        $itemKey = array_key_first($item);
        if (is_array($item[$itemKey])) {
          $this->arrayToQuery($item[$itemKey]);
        }
        $item = (new Query($itemKey))->setSelectionSet($item[$itemKey]);
      }
    }
  }

}
