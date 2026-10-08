# ZKTeco ACC PUSH deployment

F22 terminals reporting `DeviceType=acc` receive a Security PUSH handshake instead
of the attendance `GET OPTION FROM` reply. The server negotiates legacy protocol
3.0.1, with HTTPS providing transport encryption. SDK 3.1.x message encryption and
door access policy management are outside this implementation.

Protocol reference: ZKTeco Security PUSH Communication Protocol, March 2020,
sections 7, 10, 12.1.1.1, and appendices 2, 5, 6, 13, 14:
https://www.scribd.com/document/604031919/Security-PUSH-Communication-Protocol-20200325-002

## Deploy

Deploy the changed application/config/routes files, the new `App\Biometric\AccPush`
class, and the `2026_10_09_000001_add_acc_push_state_to_biometric_devices.php` migration.
Run from the live Laravel project:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=iclock
tail -f storage/logs/laravel.log
```

Keep ADMS selected, domain name enabled, server `fitness.devintek.com`, port 443,
HTTPS enabled, proxy disabled. Restart the terminal after deployment if it does
not retry automatically. Do not reset it or change its Device Type.

Look for command poll diagnostics followed by command results. Existing pending
USERINFO commands are converted to ACC `DATA UPDATE user` at delivery, using
their existing command IDs. Successful results mark them done; failed results
are available in `device_commands.result`. Do not manually open `getrequest` to
test: that marks commands sent without delivering them to the terminal.

After the user appears on the terminal, enroll the fingerprint against the
portal Machine PIN and make a test punch. `ACC attendance upload` should show
parsed punches, and Setup/Test -> Refresh should show the received event.

Only confirmed successful individual access events are counted as attendance;
alarms, rejected access, remote openings, exit buttons, and empty/zero PINs are
ignored. The existing device punch mode controls in/out status versus alternating.
Realtime `rtlog` and `tabledata` transaction uploads are accepted. Numeric
`time_second` values use ZKTeco's calendar encoding, not Unix time.

Devices remain identified by registered serial number, as in the existing ADMS
flow. Registration/session metadata is stored separately from editable settings
and excluded from JSON model output. It is not a new authentication boundary.
Real F22 hardware behavior still requires verification on the live terminal.
