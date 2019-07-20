# ffxiv-datamining-patches

A complete collection of patch information from **1.23** to the current patch **5.01**. The purpose of these is to maintain an historical record. At some point reformat them into a useful data set to provide accuracy patch numbers.

Some notes:

- Why a bunch of zip files? Because un-zipped the entire size is 2+ GB.
- 1.23 structure and format is nothing like 2.0, it's ... wild

## Extract/Zip the files

- Split Files: `bash zip_split`
- Join Files: `bash zip_join`
- Extract `extract.zip` as normal to /extracts
  - Folder example: `ffxiv-datamining-patches\extracts\2.00\Achievement.exd`
- Zip: `php zipup.php`

## Patch builder list

A `build.php` file is included which will run through all patches from top to bottom and build a very accurate patch list for each piece of content. It starts on the latest patch and then finds the files in previous patches until no more found (the file is new that patch), it will attempt to remove placeholders basic on a 30% probability of 5 string values being empty.

> You need to run install composer libraries: `composer install` - the vendor is included in the repo

You can run it as:

```bash
php build.php
```

If you want to run the build for a specific piece of content, add that as an argument, eg:

```bash
php build.php Achievement
```


