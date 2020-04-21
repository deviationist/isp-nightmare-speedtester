# ISP Nightmare Speedtester
This is a tool to monitor your internet connection speed using the Ookla speedtest CLI tool together with a bit of bash and PHP.

### How does it work?
It's really simple:
1. A cron triggers a bash-script every x minute (every 15 or 30 minute is recommended).
2. The bash-script runs the Ookla Speedtest binary.
3. The output of the bash-script gets appended to a logfile.
4. The logfile is read by a PHP-script which calculates and dislays the result.

### Why do this?
Some ISP's require that you must prove that your connection speed is lower than what you pay for. This tool might be helpful to document the actual delivery of bandwidth and speed.  

### Setup
Be sure to have the Ookla Speedtest CLI installed. More details here: https://www.speedtest.net/apps/cli

Also run ```composer install``` in the project root to pull in PHP dependencies.

### Cron setup
The command below will run every 15 minute:<br>
```*/15 * * * * /path/to/speedtest.sh >> /path/to/speedtest.log```<br>
Use https://crontab.guru/ if you are unsure about how to specify the time in the crontab.

### Display statistics
The results can be seen by executing the following PHP-file via the CLI:
```./average-speed.php```
Make sure that the file is executable. If not then run:
```chmod +x average-speed.php```

### Future plans
- CSV-export
- Smarter storage - maybe in a SQLite-file instead?
- A better way of generating statistics - maybe via SQL?
- Add .env-based config so that the logfile can be placed anywhere
