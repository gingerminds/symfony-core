# Changelog

All notable changes to this bundle are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Initial Symfony 8.1 port of `gingerminds/laravel-core`: resource registry, generic CRUD
  controller and route loader, repository with pagination/sort/search/filters/eager loads,
  filter handlers (date, number, boolean, select, select-entity, select-enum), facets,
  User/Contributor/Role/Permission entities (overridable mapped superclasses), voters and
  permissions, admin form login, API opaque tokens, API Platform provider/processor based on
  forms, context header documentation, API response cache,
  timestamps, Twig/Symfony UX admin theme, `make:gm:*` makers,
  `gingerminds:permissions:sync` and `gingerminds:create:user` commands.
