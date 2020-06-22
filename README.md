# :mag: Silverstripe Search-as-a-Service

[![Build Status](https://api.travis-ci.com/silverstripe/silverstripe-search-service.svg?branch=master)](http://travis-ci.com/silverstripe/silverstripe-search-service)
[![codecov](https://codecov.io/gh/silverstripe/silverstripe-search-service/branch/master/graph/badge.svg)](https://codecov.io/gh/silverstripe/silverstripe-search-service)
[![Version](http://img.shields.io/packagist/v/silverstripe/silverstripe-search-service.svg?style=flat-square)](https://packagist.org/packages/silverstripe/silverstripe-search-service)
[![License](http://img.shields.io/packagist/l/silverstripe/silverstripe-search-service.svg?style=flat-square)](LICENSE)

This module for Silverstripe CMS provides a set of abstraction layers that integrate the
CMS with a search-as-a-service provider, such as Elastic or Algolia. Out of the box, it
supports indexing DataObjects with Elastic AppSearch, but can be extended to work with
other sources of content and/or service providers.

This module does not provide any frontend functionality such as UI or querying APIs.
It only handles indexing.

## Installation

```
composer require "silverstripe/silverstripe-search-service"
```

## Requirements

* silverstripe/framework 4.4+
* silverstripe/versioned
* symbiote/silverstripe-queuedjobs

## 🚨 Before using 🚨

You must select which search service you will use after installing this module. If left undefined,
your `dev/build` process will throw a runtime error. See the [implementations documentation](docs/en/implementations.md) for more information about how to activate a search service.

## Documentation

See the [developer documentation](docs/en/index.md).
