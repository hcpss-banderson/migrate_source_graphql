<?php

namespace Drupal\migrate_source_graphql\Plugin\migrate\source;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\migrate_source_graphql\GraphQL\Client;
use GraphQL\Exception\QueryError;

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
    $configuration['data_key'] = isset($configuration['data_key']) ? $configuration['data_key'] : 'data';

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    // Endpoint is required.
    if (empty($this->configuration['endpoint'])) {
      throw new \InvalidArgumentException('You must declare the "endpoint" to the GraphQL API service in your settings.');
    }

    // Queries are required.
    if (empty($this->configuration['query'])) {
      throw new \InvalidArgumentException('You must declare the "query" parameter in your settings to get expected data from GraphQL API service.');
    }
    else {
      $headers = [];
      if (isset($this->configuration['auth_scheme']) && !empty($this->configuration['auth_scheme'])) {
        $headers['Authorization'] = $this->configuration['auth_scheme'] . ' ' . ($this->configuration['auth_parameters'] ?? '');
      }
      $this->client = new Client($this->configuration['endpoint'], $headers);
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
    try {
      $results  = $this->client->runQuery($query);
      $results  = $results->getData();
      $property = $this->configuration['data_key'];
      $results = $results->$queryName->$property ?? $results->$queryName ?? [];
      foreach ($results as $result) {
        yield json_decode(json_encode($result), TRUE);
        ;
      }
    } catch (QueryError $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
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
      $results = is_array($query['fields'][0]) ? array_keys($query['fields'][0]) : $query['fields'];
      foreach ($results as $resultKey) {
        if (isset($query['fields'][0][$resultKey])) {
          foreach ($query['fields'][0][$resultKey] as $field) {
            if (!is_array($field)) {
              $fields[$field] = $field;
            }
          }
        }
        else {
          $fields[$resultKey] = $resultKey;
        }
      }
    }
    return $fields;
  }
}
