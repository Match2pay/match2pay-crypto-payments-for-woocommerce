const repository = process.env.GITHUB_REPOSITORY;
const refName = process.env.GITHUB_REF_NAME;
const updaterUrl = `https://raw.githubusercontent.com/${repository}/${refName}/updater`;
const pluginInfoJson = `${updaterUrl}/${refName}.json`;
const pluginUrl = `https://github.com/${repository}/releases/download/v\${nextRelease.version}/match2pay-crypto-payments-for-woocommerce.zip`;

/**
 * @type {import('semantic-release').GlobalConfig}
 */
module.exports = {
    "branches": [
        {"name": "main"},
        {"name": "next", "channel": "next", "prerelease": "next"},
        {"name": "beta", "channel": "beta", "prerelease": true}
    ],
    "plugins": [
        [
            "@semantic-release/commit-analyzer",
            {
                "preset": "angular",
                "parserOpts": {
                    "noteKeywords": [
                        "BREAKING CHANGE",
                        "BREAKING CHANGES",
                        "BREAKING"
                    ]
                }
            }
        ],
        [
            "@semantic-release/release-notes-generator",
            {
                "preset": "angular",
                "parserOpts": {
                    "noteKeywords": [
                        "BREAKING CHANGE",
                        "BREAKING CHANGES",
                        "BREAKING"
                    ]
                },
                "writerOpts": {
                    "commitsSort": [
                        "subject",
                        "scope"
                    ]
                }
            }
        ],
        [
            "semantic-release-replace-plugin",
            {
                "replacements": [
                    {
                        "files": ["match2pay-crypto-payments-for-woocommerce.php"],
                        "from": "\\* Version: .*",
                        "to": "* Version: ${nextRelease.version}",
                        "countMatches": true
                    },
                    {
                        "files": ["match2pay-crypto-payments-for-woocommerce.php"],
                        "from": "define\\( 'WC_MATCH2PAY_VERSION', '.*' \\);",
                        "to": "define( 'WC_MATCH2PAY_VERSION', '${nextRelease.version}' );",
                        "countMatches": true
                    },
                    {
                        "files": ["match2pay-crypto-payments-for-woocommerce.php"],
                        "from": "define\\( 'WC_MATCH2PAY_UPDATER_URL', '.*' \\);",
                        "to": `define( 'WC_MATCH2PAY_UPDATER_URL', '${pluginInfoJson}' );`,
                        "countMatches": true
                    },
                    {
                        "files": ["scripts/template.json"],
                        "from": "__VERSION__",
                        "to": "${nextRelease.version}",
                        "countMatches": true
                    },
                    {
                        "files": ["scripts/template.json"],
                        "from": "__URL_UPDATER__",
                        "to": updaterUrl,
                        "countMatches": true
                    },
                    {
                        "files": ["scripts/template.json"],
                        "from": "__PLUGIN_ASSET_URL__",
                        "to": pluginUrl,
                        "countMatches": true
                    },
                    {
                        "files": ["README.txt", "README.md"],
                        "from": "Stable tag: .*",
                        "to": "Stable tag: ${nextRelease.version}",
                        "countMatches": true
                    }
                ]
            }
        ],
        [
            "@semantic-release/changelog",
            {
                "changelogFile": "CHANGELOG.md"
            }
        ],
        [
            "@semantic-release/exec",
            {
                "prepareCmd": `npm run build:updater ${refName}`
            }
        ],
        [
            "@semantic-release/git",
            {
                "assets": [
                    "match2pay-crypto-payments-for-woocommerce.php",
                    "package.json",
                    "CHANGELOG.md",
                    "updater/main.json",
                    "updater/beta.json",
                    "updater/next.json",
                    "README.txt",
                    "README.md",
                ],
                "message": "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
            }
        ],
        [
            "@semantic-release/exec",
            {
                "prepareCmd": "npm run plugin-zip"
            }
        ],
        [
            "@semantic-release/github",
            {
                "assets": [
                    {
                        "path": "./match2pay-crypto-payments-for-woocommerce.zip",
                        "label": "match2pay-crypto-payments-for-woocommerce.zip"
                    }
                ]
            }
        ]
    ]
}
