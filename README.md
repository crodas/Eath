Eath
====

Super simple package installer for PHP.

<h4>This is an alpha project, things will be broken from time to time.</h4>



What can I do for you?
----------------------

1. Install *any* archive (zip, tar, tar.gz, tar.bz2)
2. Install packages from github (github:<author>/<project>)
3. Install extensions (only tested on Linux)
4. Install things globaly (not yet fully tested)
5. Create and install binary files (`phar` files with every dependency in it) and place it in the executable path.

How does it work?
-----------------

`Eath` will basically unpack the content of the archive inside the `packages/` directory, then will build an `autoload file`, splitting them into chunks so it would tend to be small regardless of how many libraries you have installed.

Any `archive` with PHP source in it (or config.m4 and C files) can be installed, however it is always better if a `package.yml` file is defined in the archive.

Supported schemas
-----------------

1. `http`
2. `https`
3. `github:user/project`
4. `ext-<pecl name>`. You should be root to install any extension
5. Any local path without file://

How to use it?
--------------

```bash
wget -c https://raw.github.com/crodas/Eath/master/eath.phar
php eath.phar install <url>
```

What does it means?
-------------------

http://en.wiktionary.org/wiki/eath

Package.yml
------------

The `package.yml` is optional, because our goal is to be able to install *anything*. The package.yml tell us the project name (otherwise it is guessed) and the version, as well as many other useful information (which files should be installed, which binaries should be created).

```yaml
name: "Project name" # Required
version: "0.0.1-dev" # Required 

#Binary 
bin: 
    foobar: cli.php  # Install, if possible foobar command and it should call to cli.php

# Tell to eath which files should be installed also or, if you won't wish to install it, 
# which files you would like to also include in the autoload file
files: [lib/]

# What things should be installed?
dependencies:
    - github:symfony/Console
    - github:symfony/Filesystem
    - github:symfony/Process
    - github:symfony/Finder
    - github:symfony/Yaml
    - github:crodas/Autoloader
```

TODO
----

1. Unit tests (urgent)
2. Documentation
3. Dependency solver, something like http://en.opensuse.org/openSUSE:Libzypp_satsolver 
4. Add HTTP authentication
5. Commands to generate `package.yml`
