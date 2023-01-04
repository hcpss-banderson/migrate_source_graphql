This module extends the list of core migrations source by adding the new **GraphQL** query source.

What's means?
-------------

This means that like as _[Migrate Source CSV](https://www.drupal.org/project/migrate_source_csv)_, for example, you can create a migration YAML file inside of which configure the GraphQL endpoint and write the query from which to extract the contents.

How it works?
-------------
### Source options
The **graphql** _source_ offers a number of options to allow you to best configure your query. In short:
```
source:
  plugin: graphql

  # [mandatory] The GraphQL endpoint
  endpoint: <value>
  # [optional] Basic, Bearer, Digest, etc...
  auth_scheme: <value>
  # [optional] Pared with auth_scheme will generate the string that
  # will be passed to the Authorization header
  auth_parameter: <value>
  # [optional] Used to specify a different name for "data" property
  data_key: <value>
  # [mandatory] from here starts the query definition
  query:
    # [mandatory] The query name
    <query_name>:
      # [optional] Query arguments (filtering, pagination, and so on...).
      # See the example below.
      arguments:
      # [mandatory] Query fields definition
      fields:
        # [mandatory] It is 'data' if no different value has been set in data_key
        - <data|data_key>:
            - <field_1>
            - <field_2>
            - <field_n>
```

### Let's take an example.

For this example we will use the [GraphQLZero](https://graphqlzero.almansi.me/ "Fake Online GraphQL API for Testing and Prototyping") (Fake Online GraphQL API for Testing and Prototyping) from which we will migrate some entities, that on GraphQLZero are called **posts**, into the our Drupal instance populating the our's default articles. Follow GraphQL query shows how to get a list of posts from GraphQLZero:

```
query {
  posts {
    data {
      id
      title
      body
    }
  }
}
```

**The response:**
```
{
  "data": {
    "posts": {
      "data": [
        {
          "id": "1",
          "title": "sunt aut facere repellat",
          "body": "quia et suscipit\nsuscipit ..."
        },
        ...
        {
          "id": "3",
          "title": "ea molestias",
          "body": "et iusto sed quo iure\nvoluptatem ..."
        },
      }
    }
```


The migration
-------------

So we, as first thing, have to setup the YAML migration file. As like as follow:
```
id: migrate_graphql_posts_articles
label: 'Migrate posts from GraphQLZero'
migration_tags: null
source:
  plugin: graphql
  endpoint: 'https://graphqlzero.almansi.me/api'
  auth_scheme: Bearer
  auth_parameter: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
  query:
    posts:
      arguments:
        options:
          paginate:
            page: 1
            limit: 10
      fields:
        -
          data:
            - id
            - title
            - body
  ids:
    id:
      type: string
process:
  title: title
  body: body

destination:
  plugin: 'entity:node'
  default_bundle: article

migration_dependencies: {  }
```
The only difference between the GraphQL query and the YAML transposition is the mandatory property **fields**, the usefulness of which is solely a matter of choice for the developer.


### From 2.0.0 version

Starting from module's stable version (2.0.0) there Is the possibility to specify a different name for "data" property, using the **data\_key** configuration parameter inside the migration YAML file. If no value was specified the default "data" one will be used as default. For example, if the our data structure returns a response like as follow:

```
query {
  posts {
    anotherBrickInTheWall {
      id
      title
      body
    }
  }
}
```

We can change the way as the module access to the posts data specifing the different data\_key value like as follow:
```
id: migrate_graphql_posts_articles
...
source:
  plugin: graphql
  data_key: anotherBrickInTheWall
  ...
  query:
    posts:
      fields:
        -
          anotherBrickInTheWall:
            - id
            - title
            - body
...
```
Additionaly it's possible to specify different authorization schemes using the **auth\_scheme** (Basic, Bearer, Digest, etc...) and **auth\_parameters** options. Simply pairing the auth\_scheme and auth\_parameters value, will generate the string that will be passed to the _Authorization_ header.

It's also possible to specify the **query arguments** using the `arguments` key under query's name (in our example _post_), look at the following example.

We need to get the paginated posts, e.g. the first ten posts from the first page; which in GraphQL will be:
```
query {
  posts(options:{
      paginate: {
        page:1,
        limit:10
      }
    }) {
    anotherBrickInTheWall {
      id
      title
      body
    }
  }
}
```
To do this with our GraphQL source plugin, we modify the migration by adding the "**arguments**" parameter as follows:
```
id: migrate_graphql_posts_articles
...
source:
  plugin: graphql
  data_key: anotherBrickInTheWall
  ...
  query:
    posts:
      arguments:
        options:
          paginate:
            page: 1
            limit: 10
      fields:
        -
          anotherBrickInTheWall:
            - id
            - title
            - body
...
```


Now that we have created (and imported) the new migration, named migrate\_plus.migration.migrate\_graphql\_posts\_articles.yml, we must execute the _migration-import_ command: \
`drush mim migrate_graphql_posts_articles` .
