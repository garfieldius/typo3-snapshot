# TYPO3 snapshot

This is a TYPO3 extension for creating snapshots of the current installation on
the CLI. It can create and restore them, make stubs for bigger file assets and
anonymize DB data.

**IMPORTANT**: Currently this works with MySQL or compatibles only!

## Installation

Use composer to add it `composer require grossberger-georg/typo3-snapshot` or
load from TER and install it in the Extension Manager.

The programs `tar` and `gzip` must be available in `$PATH` / `%PATH%`.

## Usage

The extension will add two new commands: `snapshot:create` and
`snapshot:restore`. They can be invoked using the TYPO3 CLI script:
`./vendor/bin/typo3 snapshot:create|restore`. If the bin-dir setting is changed
in the composer.json manifest, the commands listed here must be run accordingly.

### Creating a snapshot

Running `snapshot:create` will create a snapshot inside the folder
`snapshot/`, which is inside `ROOT_PATH/var` or `DOCUMENT_ROOT/typo3temp/var`,
depending on the setup. A snapshot has a name, which is determined by the name
of the folder its data is in.

The command takes an optional argument: the name of the snapshot to create. If
no name is given, the current timestamp will be used and printed to stdout:

```bash
# Will create a snapshot in var/snapshot/dev/
vendor/bin/typo3 snapshot:create dev

# Will create a snapshot in var/snapshot/YYYYMMDDHHMMSS/
vendor/bin/typo3 snapshot:create
```

There are three additional switches:

| Full        | Short | Description                                                                           |
|-------------|-------|---------------------------------------------------------------------------------------|
| --files     | -f    | Add data of local storage drivers to the snapshot. By default, this is the fileadmin  |
| --small     | -s    | Ensure a small snapshot by creating stubs and only saving referenced files            |
| --anonymize | -a    | Anonymize data in user tables. Defaults to sys_log, be_users, fe_users and tt_address |

#### The `--files` switch

For each FAL storage using the Local Driver, a tar archive is created,
containing all files inside it. In a typical installation, this is the content
of the folder *fileadmin/*. Only the directory *\_processed_* is excluded.

It will create a file named `[STORAGE_UID]--[STORAGE_NAME].tar.gz` for each
storage using the Local driver. For the fileadmin, a snapshot will contain
its content in the archive file `1--fileadmin.tar.gz`.

#### The `--small` switch

This is an addition to `--files`. Adding the switch `--small` has no effect
without `--files`.

It reduces the size of a snapshot via two simple measures:

**1. Creating stubs:**

This will reduce the size of files which are larger than 100KB.

In case of
images in a web format (gif, png, jpg and webp), as well as PDF files, a
placeholder is generated, containing only the file name as text. Images will
be the same dimensions as their originals. PDFs will have only one page.

Other filetypes, like binary downloads, will have their content replaced with
the MD5 checksum of the original content. Their size will be reduced to a fixed
16 bytes. This means that files like ZIPs or executables will not work anymore!

**2. Only adding referenced files**

The table `sys_file_reference` is checked if a file in a storage is actually
used on the page. If it is not, it is not added to the snapshot.

#### The `--anonymize` switch

This will anonymize possibly sensitive data in the exported records by
pseudonymization and removal of certain pieces of information.

Currently, it works out of the box with `sys_log`, `fe_user`, `be_users` and
`tt_address`.  Only fields with not-empty values will be processed,
empty fields are are left empty.

In case of login information (fe_users and be_users), the fields containing
the username and the password are not changed. This is because this information
is functional and cannot be changed without possible side effects. Also internal
fields, like the UID, will not be changed.

### Restoring a snapshot

This will restore a snapshot inside the folder var/snapshot by importing
the database and, if enabled via the switch, unpacking the files into the root
of their storages:

```bash
# Restore latest snapshot, including files
vendor/bin/typo3 snapshot:restore --files

# Restore snapshot "dev" without files, database only
vendor/bin/typo3 snapshot:restore dev
```

In case the `name` argument is not set, the snapshot is determined by using
the first entry of an alphabetically sorted list of available snapshots. By
default, snapshots are named using a timestamp, so the latest will be used.

Without the switch `--files`, only the database is restored.

## Credits

The anonymizer uses the following static data sets for its operations:

* Personal names are based on the docker names-generator: <https://github.com/moby/moby/blob/master/pkg/namesgenerator/names-generator.go>
* Company names are taken from the Fortune 500 List (2018): <http://fortune.com/fortune500/list>
* Hosts are the main domain names of the 100 biggest websites.
* Countries is the list of english country names from EXT:static_info_tables v6.7.4
* Cities is the list of country capitals from EXT:static_info_tables v6.7.4

## License

[Apache 2.0](https://www.apache.org/licenses/LICENSE-2.0)
