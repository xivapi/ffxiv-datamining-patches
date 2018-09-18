# ffxiv-datamining-patches

A complete collection of patch information from **1.23** to the current patch **4.4**

Some notes:

- Why a bunch of zip files? Because un-zipped the entire size is 2GB.
- 1.23 structure and format is nothing like 2.0, it's ... wild
- 2.1 is a "recovered" patch, I cannot find an `exd` extract for 2.1 and have pulled Name+ID from a backup of XIVDB
- Patches: 2.0, 2.2 --> 2.51 structure is out of date, no column headings provided.
- Patches 2.55 to current use SaintCoinach `exd` command and should be familar.

The purpose of these is to maintain an historical record. At some point reformat them into a useful data set to provide accuracy patch numbers.

## Extracted folders

Only root data is included, the following folders are not included:

- /custom
- /dungeon
- /guild_order
- /individual
- /leve
- /opening
- /quest
- /story
- /system
- /transport
- /warp

```
1.23
2.0 - exd
2.1 - recovery
2.20 - exd
2.25 - exd
2.28 - exd
2.30 - exd
2.35 - exd
2.38 - exd
2.40 - exd
2.45 - exd
2.50 - exd
2.51 - exd
2.55
3.0
3.01
3.05
3.07
3.1
3.15
3.2
3.25
3.3
3.35
3.4
3.45
3.5
3.55a
3.55b
3.56
4.0
4.01
4.05
4.1
4.11
4.15
4.2
4.25
4.3
4.31
4.35
4.4
```

## Patch builder list

A `build.php` file is included which will run through all patches from top to bottom and build a very accurate patch list for each piece of content. It starts on the latest patch and then finds the files in previous patches until no more found (the file is new that patch), it will attempt to remove placeholders basic on a 30% probability of 5 string values being empty.

> You may need to run install composer libraries: `composer install` - the vendor is included in the repo

You can run it as:

```bash
php build.php
```

If you want to run the build for a specific piece of content, add that as an argument, eg:

```bash
php build.php Achievement
```
