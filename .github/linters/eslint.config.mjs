export default [
    {
        files: ['assets/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2020,
            sourceType: 'script',
            globals: { window: 'readonly', document: 'readonly', localStorage: 'readonly', console: 'readonly' },
        },
        rules: {
            'no-undef': 'error',
            'no-unused-vars': 'error',
            'no-redeclare': 'error',
            'no-dupe-keys': 'error',
            'no-unreachable': 'error',
            'no-eval': 'error',
            'no-implied-eval': 'error',
            eqeqeq: 'error',
            'no-console': 'warn',
        },
    },
];
