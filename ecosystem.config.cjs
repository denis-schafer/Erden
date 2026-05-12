module.exports = {
    apps: [{
        name: 'reverb',
        script: 'artisan',
        args: 'reverb:start --host=0.0.0.0',
        cwd: __dirname,
        interpreter: 'C:\\laragon\\bin\\php\\php-8.2.30-nts-Win32-vs16-x64\\php.exe',
        interpreter_args: '',
        watch: false,
        autorestart: true,
        exp_backoff_restart_delay: 100,
        max_restarts: 10,
        env: {
            NODE_ENV: 'production',
        },
        error_file: __dirname.replace(/\\/g, '/') + '/storage/logs/reverb-error.log',
        out_file: __dirname.replace(/\\/g, '/') + '/storage/logs/reverb-out.log',
        log_file: __dirname.replace(/\\/g, '/') + '/storage/logs/reverb-combined.log',
        time: true,
    }]
};
