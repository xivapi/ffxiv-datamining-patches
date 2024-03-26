# ffxiv-datamining-patches

A complete collection of patch information from **1.23** to the current patch. The purpose of these is to maintain an historical record. At some point reformat them into a useful data set to provide accuracy patch numbers.

## Adding a new patch entry

In order to update content, you need to run this on each patch, after client files update has been done.

After having installed deps with `yarn`, simply do:

```shell
yarn start
```

It'll prompt for a new patch to be added, which you can refuse if you did add one (which is **the recommended way on expansions since you need to set new expansion name and version**). If you said yes, version number will be asked.

Extraction takes few seconds and will update files in `patchdata/${sheetName}.json`.