import { promises as fs } from 'fs';
import { readFile, writeFile } from 'fs/promises';
import { XMLParser } from 'fast-xml-parser';
import {
    join,
    sep,
    dirname,
} from 'path';
import pgPromise from 'pg-promise';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';
const lineLength = 75;
const spaceCount = 4;
let dotenvJson = null;
let packageJson = null;
let manifest = null;
let db = null;
const legacyInfoPath = join('App', 'info.xml');
const infoName = 'plugin.xml';
const infoPath = join(infoName);


const __dirname = import.meta.dirname || dirname(fileURLToPath(import.meta.url));

/**
 * Definition of all available options.
 */
const availableOptions = {
    call: {
        arguments: ['-c', '--call'],
        description: 'Call the doctor: an automatic process that runs a series of checks ensuring that the plugin is working with the current Spacialist',
        callback: call
    },
    db: {
        arguments: ['-d', '--db'],
        description: 'Check the database connection',
        callback: callDatabase
    },
    help: {
        arguments: ['-h', '--help'],
        description: 'Display this help message',
        callback: callHelp
    },
    id: {
        arguments: ['-i', '--id'],
        description: 'Get the plugin id',
        callback: callId
    },
    linkLegacyInfo: {
        arguments: ['-li', '--link-legacy-info'],
        description: 'Creates a symlink from the info file to the legacy info location',
        callback: callLinkLegacyInfo,
    },
    linkLib: {
        arguments: ['-l', '--link-lib'],
        description: 'Creates symlinks to the lib folder',
        callback: callLinkFolders,
    },
    linkJs: {
        arguments: ['-L', '--link-js'],
        description: 'Creates symlinks to the js folder',
        callback: callLinkJs,
    },
    manifest: {
        arguments: ['-m', '--manifest'],
        description: `Prints the path to the '${infoName}' file`,
        callback: callManifest,
    },
    path: {
        arguments: ['-p', '--path'],
        description: 'Prints the path to the plugin directory',
        callback: callPath,
    },
    renameDirectory: {
        arguments: ['-r', '--rename-directory'],
        description: 'Renames the plugin directory to the name in package.json',
        callback: callRenameDirectory,
    },
    verifyPackageJson: {
        arguments: ['-v', '--verify-package-json'],
        description: 'Verifies the `package.json` file',
        callback: callVerifyPackageJson,
    },
    verifyVersion: {
        arguments: ['-V', '--verify-version'],
        description: 'Verifies the version of the plugin',
        callback: callVerifyVersion,
    }
};

/**
 * Main function called when the script is executed.
 */
async function main() {
    const {
        errors,
        unknownArguments,
        duplicateArguments,
        options,
    } = parseOptions();

    if(errors || Object.keys(options).length > 1) {
        let message = '';
        if(Object.keys(options).length > 1) {
            message += 'You can only pass one option at a time.\n';
        }
        if(unknownArguments.length > 0) {
            message += 'Unknown arguments: ' + unknownArguments.join(', ');
        }
        if(duplicateArguments.length > 0) {
            message += 'Duplicate arguments: ' + duplicateArguments.join(', ');
        }
        message += '\nPlease use -h or --help to see the available options.';
        error(message);
        return;
    }

    await Object.values(options)[0].callback();
}

/**
 * Execution of the main function.
 * No other code should be executed on the top level.
 */
try {
    await main();
    process.exit(0);
} catch(e) {
    error(e.message);
    process.exit(1);
}

/**
 * ================  Call the database function  ========================
 */
async function callDatabase() {
    startSection('Check database');
    try {
        const {
            DB_HOST,
            DB_PORT,
            DB_DATABASE,
        } = await getEnv();
        log(`\n\nDatabase connection details:
============================
Host: ${DB_HOST}
Port: ${DB_PORT}
Datbase name: ${DB_DATABASE}
`);
    } catch(e) {
        error(`Could not read the environment variables: ${e.message}`);
        return;
    }
}

/**
 * ================  Call the help function  ========================
 */

function callHelp() {
    log(`🩺 Usage: doctor [options]
    Options:
        ${getOptionsText()}
    `);
}

function getOptionsText() {

    let maxLength = 0;
    Object.values(availableOptions).forEach((option) => {
        const length = option.arguments.join(', ').length;
        if(length > maxLength) {
            maxLength = length;
        }
    });

    return Object.values(availableOptions).map((option) => {
        return option.arguments.join(', ').padEnd(maxLength + 4, ' ') + option.description;
    }).join('\n        ');
}

/**
 * ================  Call the id function  ========================
 */

async function callId() {
    startSection('Get plugin id');
    try {
        const uuid = await getPluginsUUID();
        success(`Plugin id:\n${uuid}`);
    } catch(e) {
        error(e.message);
    }
}

/**
 *  ================  Call the path function  ========================
 */
async function callPath() {
    try {
        const isInPluginDir = await checkIfInPluginDir();
        if(isInPluginDir) {
            success(`The plugin is in a Spacialist's plugin directory!`);
        } else {
            error(`The plugin is not in a Spacialist's plugin directory! 
The spacialist plugin directory is located at: 'app/Plugins/{PluginName}'`);
        }
    } catch(e) {
        systemError(`Application path could not be checked: ${e.message}`);
    }
}

async function checkIfInPluginDir() {
    let isInPluginDir = false;
    const pluginDir = getSpacialistPath('package.json');
    const content = await fs.readFile(pluginDir, 'utf-8');
    const packageJson = JSON.parse(content);
    if(packageJson.name?.toLowerCase && packageJson.name.toLowerCase() === 'spacialist') {
        isInPluginDir = true;
    }
    return isInPluginDir;
}

function getSpacialistPath(...dest) {
    const spacialistRoot = join(__dirname, '..', '..', '..');
    return join(spacialistRoot, ...dest);
}

async function getSpacialistPluginJsFolder(path = []) {
    if(!Array.isArray(path)) {
        path = [path];
    }
    return getSpacialistPath('storage', 'app', 'private', 'plugins', ...path);
}

async function getDeployedScriptFileName() {
    let uuid = null;
    try {
        uuid = await getPluginsUUID();
    } catch(e) {
        error(e.message);
        return;
    }

    if(!uuid) {
        error('Plugin UUID not found');
        return;
    }

    const { pluginName } = await getPackageJsonValues();
    return `${pluginName.toLowerCase()}-${uuid}.js`;
}

/** 
 * ================  Call the manifest function  ========================
 */

async function callManifest() {
    startSection('Check if a manifest file exists');
    let succeeded = {};
    for(const file of [infoPath, legacyInfoPath, 'lib/App/info.xml']) {
        const errorMessage = await hasManifestErrors(file);
        if(errorMessage) {
            succeeded[file] = false;
            warning(`Manifest file "${file}" is not valid: ${errorMessage}`);
        } else {
            succeeded[file] = true;
            success(`Manifest file "${file}" is valid.`);
        }
    }

    if(!succeeded[legacyInfoPath] && succeeded['lib/App/info.xml']) {
        hint(`It seems like your plugin uses the "lib" folder for the app. You must create the symlinks first.`);
    }
}

async function hasManifestErrors(path) {
    try {
        await parseManifest(path);
    } catch(e) {
        return 'file does not exist';
    }
    return false;
}

async function useManifest(path) {
    if(manifest) return manifest;

    for(const path of [infoPath, legacyInfoPath]) {
        try {
            manifest = await parseManifest(join(__dirname, path));
        } catch(e) {
            continue;
        }
        if(manifest) {
            return manifest;
        }
    }

    if(!manifest) {
        throw new Error('Manifest file not found');
    }
}

async function parseManifest(path) {
    const manifest = await readFile(path);
    // Keep values as strings, otherwise a version like "1.0" is parsed as the number 1
    const xmlParser = new XMLParser({ parseTagValue: false });
    const parsedManifest = xmlParser.parse(manifest);
    if(!parsedManifest) {
        throw new Error('File is not a valid XML file: ' + path);
    }
    return parsedManifest;
}

/** 
 * ================  Call link legacy info.xml  ========================
 */

async function callLinkLegacyInfo() {
    startSection('Link legacy info.xml');
    const abslegacyInfoPath = join(__dirname, legacyInfoPath);
    const absinfoPath = join(__dirname, infoPath);

    try {
        await fs.access(absinfoPath);
    } catch(e) {
        error(`Actual '${infoName}' file does not exist at ${absinfoPath}`);
        return;
    }

    try {
        await fs.symlink(absinfoPath, abslegacyInfoPath);
        success(`Symlink created from ${absinfoPath} to ${abslegacyInfoPath}`);
    } catch(e) {
        error(`Failed to create symlink from ${absinfoPath} to ${abslegacyInfoPath}:`, e);
    }
}

/**
 * ================  Call the link-lib function  ========================
 */

async function callLinkFolders() {
    startSection('Link lib folder');
    const libDirName = 'lib';
    let dirNames = [];
    const libDirectory = join(__dirname, libDirName);
    try {
        dirNames = await fs.readdir(libDirectory);
    } catch(e) {
        warning(`Plugin has no '${libDirName}' directory! Linking not necessary (or not possible.)`, e);
        return;
    }
    // Create symlink in parent directory
    for(const dir of dirNames) {
        log(`Checking if "${dir}" exists...`);
        const srcPath = join(libDirectory, dir);
        const destPath = join(__dirname, dir);

        try {
            //Check if the directory exists
            const content = await fs.readdir(destPath);
            stale(`Directory "${dir}" already exists!`);
            continue;
        } catch(e) {
            // If file does not exist, continue
        }

        log(`Create symlink ${srcPath} -> ${destPath} ...`);
        try {
            // // There are problems with symlinks on windows
            // // when deleted they still exist somehow
            // // so we need to delete them first.
            // if (existsSync(destPath)) {
            //     warning(`Directory "${dir}" already exists! Deleting it...`);
            //     await fs.unlink(destPath);
            // }

            await fs.symlink(srcPath, destPath);
            success(`Symlink successfully created for ${srcPath}!\n`);
        } catch(e) {
            error(`Failed to create symlink for ${srcPath}!`, e);
        }
    }
}

/**
 * ================  Call the link-js function  ========================
 */

async function callLinkJs() {
    startSection('Link js folder');

    const srcPath = await getUmdFilePath();
    try {
        await fs.readFile(srcPath);
        success(`File "${srcPath}" exists!`);
    } catch(e) {
        error(`File "${srcPath}" does not exist!`);
        return;
    }

    try {
        await fs.access(join(__dirname, 'js', 'script.js'));
        warning(`File "js/script.js" already exists!`);
        await ensureSourceMap();

    } catch(e) {
        try {
            await fs.access(join(__dirname, 'js'));
            success(`Directory "js" already exists!`);
        } catch(e) {
            try {
                await fs.mkdir(join(__dirname, 'js'));
                success(`Directory "js" created!`);
            } catch(e) {
                error(`Failed to create directory "js": ${e.message}`);
                return;
            }
        }
        try {
            await fs.symlink(srcPath, join(__dirname, 'js', 'script.js'));
            success(`Symlink successfully created for "js/script.js"!`);
            await ensureSourceMap();
        } catch(e) {
            error(`Failed to create symlink for "js/script.js": ${e.message}`);
        }
    }

    const fileName = await getDeployedScriptFileName();
    const destPath = await getSpacialistPluginJsFolder(fileName);
    try {
        await fs.readFile(destPath);
        warning(`File "${destPath}" already exists!`);
        return;
    } catch(e) {
        // If file does not exist, continue
    }

    try {
        await fs.symlink(srcPath, destPath);
        success(`Symlink successfully created for ${srcPath} -> ${destPath}`);
    } catch(e) {
        error(`Failed to create symlink for ${srcPath} -> ${destPath}:`, e);
    }
}

/**
 * ================  Call the rename-directory function  ========================
 */

async function callRenameDirectory(packageJson = null) {
    startSection('Rename directory');
    let name = null;
    if(!packageJson) {
        const { pluginName } = await getPackageJsonValues();
        name = pluginName;
    } else {
        name = packageJson.pluginName;
    }
    const originalDir = __dirname;
    const parts = originalDir.split(sep);
    parts.pop();
    parts.push(name);
    const renamedDir = parts.join(sep);

    if(originalDir === renamedDir) {
        success(`Direcory name is corretly set!`);
    } else {
        //await fs.rename(originalDir, renamedDir)
        error(`Wrong directory name: expected ${renamedDir}, got ${originalDir}`);
    }
}

/**
 * ================  Call the verify-package-json function  ========================
 */

async function callVerifyPackageJson() {
    startSection('Verify package.json');
    const { name, pluginName, version, description, missingFields } = await getPackageJsonValues();

    if(missingFields.length > 0) {
        error(`Missing required fields in package.json: ${missingFields.join(', ')}`);
        return;
    }

    success(`Your package.json is valid!`);
    log(`Name: ${name}`);
    log(`Plugin Name: ${pluginName}`);
    log(`Version: ${version}`);
    log(`Description: ${description}`);
}

async function getPackageJsonValues() {
    const packageJson = await usePackageJson();
    const missingFields = [];
    const requiredFields = ['name', 'pluginName', 'version', 'description'];
    requiredFields.forEach((field) => {
        if(packageJson[field] == null) {
            missingFields.push(field);
        }
    });

    return {
        name: packageJson.name,
        pluginName: packageJson.pluginName,
        version: packageJson.version,
        description: packageJson.description,
        missingFields,
    };
}

/**
 * ================  Call the verify-version function  ========================
 */

async function callVerifyVersion() {
    startSection('Verify version');
    let packageJson = null;
    let manifest = null;
    try {
        manifest = await useManifest();
        packageJson = await usePackageJson();
    } catch(e) {
        error(`Manifest file not found: ${e.message}`);
        return;
    }

    // <plugin> is the 0.12 root element, <info> the legacy one
    const manifestVersion = (manifest?.plugin ?? manifest?.info)?.version;
    const packageJsonVersion = packageJson.version;

    if(!manifestVersion) {
        error(`The '${infoName}' does not contain a version`);
    }

    if(manifestVersion !== packageJson.version) {
        warning(`Version in ${infoName} (v${manifestVersion}) did not match version in package.json (v${packageJsonVersion}). Updating package.json version to match ${infoName} => v${manifestVersion}`);
        packageJson.version = manifestVersion;
        await writeFile('package.json', JSON.stringify(packageJson, null, 4));
    } else {
        success(`Version in ${infoName} matches version in package.json: v${manifestVersion}`);
    }
}


/**
 * ================  Call the doctor function  ========================
 * This function is called when the -c or --call option is passed
 * It will do a complete health check of the plugin and print the results
 * to the console.
 */

async function call() {
    log(separator());
    startSection('Call the doctor');
    log(separator() + '\n');


    await callVerifyPackageJson();
    await callVerifyVersion();
    await callRenameDirectory();
    await callLinkFolders();
    await callLinkJs();
}

async function getPluginsUUID() {
    const db = await useDB();
    const { pluginName } = await getPackageJsonValues();
    const result = await db.oneOrNone('SELECT uuid FROM plugins where name=$1', [pluginName]);
    const isPackageInstalled = result == null ? false : true;

    if(!isPackageInstalled) {
        throw new Error('Package is not installed yet. You must install it manually in spacialist.');
    }

    const uuid = result.uuid;
    return uuid;
}

async function useDB() {
    if(!db) {
        const programConfig = await getEnv();
        const pgp = pgPromise();
        db = pgp({
            host: programConfig.DB_HOST,
            port: programConfig.DB_PORT,
            database: programConfig.DB_DATABASE,
            user: programConfig.DB_USERNAME,
            password: programConfig.DB_PASSWORD,
        });
    }
    return db;
}

async function getEnv() {
    if(!dotenvJson) {
        const dotEnvFile = await fs.readFile(getSpacialistPath('.env'), 'utf-8');
        dotenvJson = dotenv.parse(dotEnvFile);
    }
    return dotenvJson;
}

async function usePackageJson() {
    if(!packageJson) {
        packageJson = await parsePackageJson();
    }
    return packageJson;
}


async function parsePackageJson() {
    let fileContent;
    try {
        fileContent = await fs.readFile('package.json', 'utf-8');
    } catch(e) {
        throw new Error(`No package.json found`);
    }

    let packageJson;
    try {
        packageJson = await JSON.parse(fileContent);
        if(!packageJson) {
            throw new Error();
        }
    } catch(e) {
        throw new Error(`Invalid "package.json" is not a valid JSON file`);
    }

    return packageJson;
}

/**
 * ================================================================
 * ====================  Helper functions  ========================
 * ================================================================
 */

/** 
 * Filenames
 */

async function getUmdFilename() {
    const { pluginName } = await getPackageJsonValues();
    return pluginName.toLowerCase() + '.umd.js';
}

async function getUmdFilePath() {
    return join(__dirname, 'dist', await getUmdFilename());
}

/**
 * ================  Creating File Structure  ========================
 */

async function ensureSourceMap() {
    const umdFilePath = await getUmdFilePath();

    // Check if the source map file exists
    const sourceMapFilePath = umdFilePath + '.map';
    try {
        await fs.access(sourceMapFilePath);
        success(`Source map file exists: ${sourceMapFilePath}`);
    } catch {
        warning(`Source map file does not exist: ${sourceMapFilePath}`);
        return;
    }

    let sourceFileName = await getDeployedScriptFileName();
    const deployedSourceFileLocation = await getSpacialistPluginJsFolder(sourceFileName + '.map');
    try {
        await fs.access(deployedSourceFileLocation);
        success('Source file is already deployed.');
    } catch(e) {
        try {
            await fs.symlink(sourceMapFilePath, deployedSourceFileLocation);
            success(`Source map file successfully linked to ${deployedSourceFileLocation}`);
        } catch(e) {
            error(`Failed to create symlink: ${e.message}`);
        }
    }
}

/*
* =================  Parsing the options  ========================
*/
function parseOptions() {
    const passedArguments = process.argv.slice(2);
    // Use help as default option
    if(passedArguments.length === 0) {
        passedArguments.push('-h');
    }
    const duplicateArguments = [];
    const unknownArguments = [];
    const allAvailableOptionArguments = {};

    passedArguments.forEach((argument) => {
        const optionName = getOptionsNameByArgument(argument);
        if(optionName === null) {
            unknownArguments.push(argument);
        } else {
            if(allAvailableOptionArguments[optionName]) {
                duplicateArguments.push(argument);
            } else {
                const availableOption = availableOptions[optionName];
                allAvailableOptionArguments[optionName] = availableOption;
            }
        }
    });

    return {
        errors: duplicateArguments.length > 0 || unknownArguments.length > 0,
        unknownArguments,
        duplicateArguments,
        options: allAvailableOptionArguments,
    };
}

function getOptionsNameByArgument(argument) {
    for(const availableOptionKey in availableOptions) {
        const availableOption = availableOptions[availableOptionKey];
        if(availableOption.arguments.includes(argument)) {
            return availableOptionKey;
        }
    }
    return null;
}

/*
* =================  Advanced Logging functions  ========================
*/

function separator(text = '') {
    let infill = (text.length === 0) ? '' : `${' '.repeat(spaceCount)}${text}${' '.repeat(spaceCount)}`;
    let symbolCount = lineLength - infill.length;
    if(symbolCount < 0) {
        symbolCount = 0;
    }
    const leftCount = Math.ceil(symbolCount / 2);
    const rightCount = Math.floor(symbolCount / 2);
    return `${'='.repeat(leftCount)}${infill}${'='.repeat(rightCount)}`;
}

function startSection(title) {
    log(separator(title));
}

function systemError(message) {
    error(`⚠   Execution error: ${message}`);
}


function success(message) {
    console.log(`\x1b[32m✔   ${message}\x1b[0m`);
}

function stale(message) {
    warning(`🟡   ${message}`);
}

function hint(message) {
    log(`ℹ Hint:   ${message}`);
}

/*
* =================  Logging functions  ========================
*/
function log(message) {
    console.log(`\x1b[34m${message}\x1b[0m`);
}

function error(message) {
    console.log(`\x1b[41m${message}\x1b[0m`);
}

function warning(message) {
    console.log(`\x1b[33m${message}\x1b[0m`);
}



