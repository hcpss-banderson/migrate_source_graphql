<?php

namespace Drupal\migrate_source_graphql\Plugin\migrate\source;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\migrate_source_graphql\GraphQL\Client;

/**
 * Class GraphQL migrate source.
 *
 * @MigrateSource(
 *   id = "graphql",
 *   source_module = "migrate_source_graphql"
 * )
 */
class GraphQL extends SourcePluginBase implements ConfigurableInterface {
  /**
   * The graphql client.
   *
   * @var \GraphQL\Client
   */
  protected $client;

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->setConfiguration($configuration);

    // Endpoint is required.
    if (empty($this->configuration['endpoint'])) {
      throw new \InvalidArgumentException('You must declare the "endpoint" to the GraphQL API service in your settings.');
    }

    // JWT Token is required, for now.
    // if (empty($this->configuration['jwt_token'])) {
    //  throw new \InvalidArgumentException('You must declare the "jwt_token" to connect to the GraphQL API service in your settings.');
    // }

    // Queries are required.
    if (empty($this->configuration['query'])) {
      throw new \InvalidArgumentException('You must declare the "query" parameter  in your settings to get expected data from GraphQL API service.');
    }
    else {
      $this->client = new Client($this->configuration['endpoint'], ['Authorization' => 'Bearer ' . $this->configuration['jwt_token']]);
      $this->fields();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'endpoint' => 'localhost',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // We must preserve integer keys for column_name mapping.
    $this->configuration = NestedArray::mergeDeepArray([
      $this->defaultConfiguration(),
      $configuration,
    ], TRUE);
  }

  /**
   * Return a string representing the GraphQL API endpoint.
   *
   * @return string
   *   The GraphQL API endpoint.
   */
  public function __toString() {
    return $this->configuration['endpoint'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   */
  public function initializeIterator() {
    return $this->getGenerator();
  }

  /**
   * Return the generator using yield.
   */
  private function getGenerator() {
    $query = $this->configuration['query'];
    $queryName = array_key_first($query);
    $query = $this->buildQuery($queryName, $query[$queryName]);
    $results  = $this->client->runQuery($query);
    $results  = $results->getData();
    $property = array_keys((array)$results->$queryName);
    $property = $property[0];
    $results = !empty($results->$queryName) ? $results->$queryName->$property : [];
    foreach ($results as $result) {
      yield json_decode(json_encode($result), TRUE);
      ;
    }
  }

  /**
   * Build the query.
   *
   * @param string $queryName
   *   Query name.
   * @param array $query
   *   The query.
   *
   * @return \GraphQL\Query
   *   Built query.
   */
  private function buildQuery(string $queryName, array $query) {
    $arguments = isset($query['arguments']) ? $query['arguments'] : NULL;
    return $this->client->buildQuery($queryName, $query['fields'], $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id']['type'] = 'string';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [];
    $query = $this->configuration['query'];
    foreach ($query as $queryName => $query) {
      $results = array_keys($query['fields'][0]);
      foreach ($results as $resultKey) {
        foreach ($query['fields'][0][$resultKey] as $field) {
          if (!is_array($field)) {
            $fields[$field] = $field;
          }
        }
      }
    }

    return $fields;
  }

}
