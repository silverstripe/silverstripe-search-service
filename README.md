# :mag: Silverstripe Search-as-a-Service

[![Build Status](https://api.travis-ci.com/silverstripe/silverstripe-search-service.svg?branch=master)](http://travis-ci.com/silverstripe/silverstripe-search-service)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-search-service/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-search-service)
[![Version](http://img.shields.io/packagist/v/silverstripe/silverstripe-search-service.svg?style=flat-square)](https://packagist.org/packages/silverstripe/silverstripe-search-service)
[![License](http://img.shields.io/packagist/l/silverstripe/silverstripe-search-service.svg?style=flat-square)](LICENSE)

This module for Silverstripe CMS provides a set of abstraction layers that integrate the CMS with a search-as-a-service
provider, such as Elastic or Algolia.

Note this module does not provide:

* Specific service integrations. See [Available service integration modules](available-service-integration-modules.md).
* Any frontend functionality such as UI or querying APIs. It only handles indexing.

## Installation

```
composer require "silverstripe/silverstripe-search-service"
```

## Requirements

* php: ^8.1
* silverstripe/cms: ^5
* symbiote/silverstripe-queuedjobs: ^5

## Documentation

See the [developer documentation](docs/en/index.md).
