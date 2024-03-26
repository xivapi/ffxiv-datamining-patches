import {buildKoboldXIV} from "@kobold/xiv";
import {Excel, Row} from "@kobold/excel";
import {ExcelList} from "@kobold/excel/dist/files";
import {readFileSync, writeFileSync} from 'fs';
import {join} from 'path';
import {prompt} from 'enquirer';
import PatchList from './patchlist.json';
// @ts-ignore
import ProgressBar from 'progress';

function generateSheetClass(sheetName: string) {
    return class DynamicRow extends Row {
        static sheet = sheetName;

        // We only want the first two columns
        columns = new Array(Math.min(this.sheetHeader.columns.length, 2))
            .fill(null)
            .map((_, i) => {
                return this.unknown({column: i});
            });

        constructor(opts: unknown) {
            super(opts as any); // Should be RowConstructorOptions but it's not exported.
        }
    }
}

(async () => {
    const shouldCreatePatchEntry = await prompt(
        {
            type: 'confirm',
            name: 'createPatch',
            message: 'Should I add an entry to patchlist.json?',
        }
    );

    let currentPatch = PatchList[PatchList.length - 1];

    if (shouldCreatePatchEntry) {
        const patchParams: { version: string, releaseDate: string } = await prompt([
            {
                type: 'input',
                name: 'version',
                message: 'Patch version number:'
            }
        ]);
        const newPatchEntry = {
            "Banner": null,
            "ID": currentPatch.ID + 1,
            "Url": `/patch+${patchParams.version}`,
            "ExVersion": currentPatch.ExVersion,
            "ExName": currentPatch.ExName,
            "IsExpansion": false,
            "Name_ja": `Patch ${patchParams.version}`,
            "Name_en": `Patch ${patchParams.version}`,
            "Name_fr": `Patch ${patchParams.version}`,
            "Name_de": `Patch ${patchParams.version}`,
            "Name_cn": `Patch ${patchParams.version}`,
            "Name_kr": `Patch ${patchParams.version}`,
            "PatchNotes_de": "",
            "PatchNotes_en": "",
            "PatchNotes_fr": "",
            "PatchNotes_ja": "",
            "ReleaseDate": Math.floor(Date.now() / 1000),
            "Version": patchParams.version
        };
        currentPatch = newPatchEntry;
        writeFileSync(join(__dirname, 'patchlist.json'), JSON.stringify([...PatchList, newPatchEntry], null, 4));
    }

    const kobold = await buildKoboldXIV();
    const excel = new Excel({kobold});
    // Get the list of all root sheets
    const excelList = await kobold.getFile('exd/root.exl', ExcelList);
    const allSheets = Array.from(excelList.sheets.keys()).filter(sheet => !sheet.includes('/'));
    const progressBar = new ProgressBar('[:bar] :current/:total', {total: allSheets.length});

    for (let sheet of allSheets) {
        const filePath = join(__dirname, 'patchdata/', `${sheet}.json`);
        const clazz = generateSheetClass(sheet);
        const data = await excel.getSheet(clazz);
        let patchData: Record<string, number> = {};
        try {
            patchData = JSON.parse(readFileSync(filePath, {encoding: 'utf8'}) || '{}');
        } catch (err) {
            // File not found
        }
        const hasSubIndexes = Object.keys(patchData).some(k => k.includes('.'));
        try {
            for await (const row of data.getRows()) {
                let key = row.index.toString();
                if (hasSubIndexes || row.subIndex > 0) {
                    key = `${row.index}.${row.subIndex}`;
                }
                if (!patchData[key] && row.columns.every(Boolean)) {
                    patchData[key] = currentPatch.ID;
                }
            }
            writeFileSync(filePath, JSON.stringify(patchData, null, 4));
        } catch (err) {
            // Error is due to subrows not supporting string values, happens on sheets like CustomTalkDefineClient
        }
        progressBar.tick()
    }
})();