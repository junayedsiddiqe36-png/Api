# Telegram Mining Mini App (PHP + MySQL)

Premium dark crypto-style Telegram Mini App + Bot + Admin Panel.
Flow: Mining -> Coins -> Referral speed -> Premium channel boost -> Coins to USD -> USDT withdrawal.

## Requirements
- Hosting with PHP 7.4+ (PHP 8.x recommended), MySQL 5.7+/MariaDB 10.3+, cURL extension
- HTTPS domain or subdomain (Telegram Mini Apps require HTTPS)
- Cron support (every minute)

## Install (5 steps)
1. Create a bot with @BotFather and copy the token. Note the bot username.
2. Create an empty MySQL database + user in your hosting panel.
3. Upload ALL files to your hosting (e.g. public_html/miner/) and open `https://yourdomain.com/miner/install.php`.
   Fill in DB, admin login, bot token and bot username. The installer creates tables, config.php, the admin account and sets the webhook.
4. Add a cron job running every minute (the installer shows your exact URL):
   `* * * * * curl -s "https://yourdomain.com/miner/cron.php?key=YOUR_KEY" >/dev/null`
   (Cron handles mining completion notices, auto-restart, payment announcements and broadcasts.)
5. Delete `install.php`, open `/miner/admin/`, log in and review **Settings**.

## Telegram setup
- **Premium channel boost:** add your bot as an **administrator** of the premium channel, then set the channel username (and ID) in Admin > Settings > Premium channel boost.
- **Payment channel:** add the bot as admin (post permission) of the payment channel, set channel + enable announcements. Only real approved/paid withdrawals are announced.
- **Tasks:** for "Join channel/group" tasks the bot must be admin in that chat so membership can be verified.
- Optional in BotFather: /setmenubutton is already done by the installer; you can also create a Main Mini App with URL `https://yourdomain.com/miner/app/`.

## How things work
- All balances and mining time are calculated on the server. The client only displays.
- Telegram initData signature is verified on every API request.
- Premium bonus is stored once per user (`premium_memberships`), so leaving and rejoining never pays twice.
- Withdrawals are **manual**: admin approves, sends the USDT yourself, then presses MARK AS PAID. Rejecting refunds the user.
- Anti-fraud: unique Telegram ID, self/duplicate referral block, one active withdrawal at a time, cooldown, daily limit, shared-payout-address flag, ban/freeze, manual adjustments + full admin log.

## Promotion channel
Admin > Settings > Promotion channel: set channel, interval (minutes), template and button. Posts contain only real numbers from your database (users, active miners, coins mined today, top miners, rate). Use the "Send promotion test post" button to test. Keep real payment announcements in the separate payment channel.

## Tap game (Admin > Tap Game)
- Mode "Tap game only" (default): when Tap is ON the old mining app is completely hidden; users get only Tap, Task, Refer and Wallet (convert, withdraw and history are inside Wallet). Other modes keep a Home button or an entry card.
- "What users tap": shiny coin or the Hamster boss with character skins.
- One switch turns the Tap & Earn mini game ON or OFF. When ON users get a separate mini app: Tap (big coin, energy bar, refill timer, Daily Rewards), Task, Refer and Wallet pages.
- Choose whether it opens from a button on the home page or opens the whole app directly in Tap mode.
- Gameplay settings: coins per tap, energy size, refill per second, max taps per second (anti-cheat), daily coin limit, token name, coin logo link.
- Separate colour settings for the Tap game (accent, background, cards, text, coin, glow) with a live preview and a reset button.
- Taps are validated on the server (energy, speed limit, daily limit). The balance is the same coin balance as the rest of the app.
- Database columns are added automatically on first load (energy, last update, taps today).

## Admin panel
- Everything is arranged in boxes: 2 columns on phones, 3 to 4 on desktop. Settings groups are boxes; tap one to open its options in a sheet.
- Admin > Theme: the "Users can change the theme" switch turns user theme choice ON or OFF.

## Gold Verse and Satoshi Gold themes, Earn page, Lucky Spin
- Admin > Theme: "Gold Verse" (blue sky, gold ore pile and a pickaxe, energy bar + Boosts) and "Satoshi Gold" (dark gold circuit board and a shining Bitcoin). Tapping the scene plays a cosmetic animation only.
- The bottom menu is now Mine | Boost | Earn | Friends | Convert. Earn has two tabs: Daily (daily reward, check-in, Lucky Spin) and Tasks.
- Lucky Spin: Admin > Settings > Lucky spin (prizes, chance weights, hours between spins). The prize is drawn on the server.
- The database upgrades itself on first load (column users.last_spin_at).

## Hamster Boss theme and character skins
- Admin > Theme: choose "Hamster Boss" (it is the default). A cute original hamster sits in the mining scene, taps squish it, and a league bar shows the miner level.
- Characters (skins): Classic, Hard Hat Miner, Office Boss, Cool Shades, Astronaut, Diamond King, Shadow Ninja, Magic Wizard.
- A skin unlocks and equips automatically when the user reaches its level ("Auto" mode). Users can equip any unlocked skin manually, go back to an earlier one, or buy a skin early with coins or USD wallet balance.
- Admin > Skins: enable/disable each skin, set the unlock level (99 = buy only) and the coin / USD prices; see how many were bought.
- On first load after this update the database is upgraded automatically (new table user_skins and two columns on users). The DB user needs ALTER permission (normal on cPanel).

## v8 highlights
- 8 animated mining scenes (Admin > Theme). Gold Rush grows with the miner level (helmet colour, hammer material, bigger Bitcoin).
- Theme modes: fixed, auto day/night, festival dates, and level-based unlocks for user-picked themes.
- Reward popups with coin rain (daily reward, check-in, tasks, level up, mining completed), welcome tour, daily reward prompt.
- Appearance sheet in the app: language (English/Bangla), theme, Reduce motion, Larger text.
- Admin dashboard with charts and KPI cards, two-column layout on desktop, Light / Dark admin.
- Add a language: copy lang/bn.php to lang/xx.php, translate, set "lang_name". It appears in the Appearance sheet automatically.

## Theme
Admin > Theme: pick one of 5 themes (Gold Rush, Ocean Crystal, Neon Night, Rose Rocket, Eid Crescent). It changes the mining artwork, coin logo, boost icons and colours. You can also allow or block users from switching themselves.

## Auto posts
Admin > Auto posts: write up to 10 messages (optional photo link each), set a channel, the minutes between posts and a button name + link (e.g. Support). The cron job posts them in order 1 to 10, then starts again at 1.

## Miner levels
Levels rise with ACTIVE referrals. Admin > Settings > Referral: level names, referrals needed per level, and the extra speed per level. Joining the premium channel adds the premium speed bonus.

## Add a language
Copy `lang/en.php` to `lang/bn.php` (or hi/zh), translate the values, then set "Language code" in Settings > Bot & app.

## Security notes
- Keep `config.php` private (the .htaccess blocks it on Apache; on Nginx add a deny rule for config.php, database.sql, includes/ and lang/).
- Use a strong admin password. Delete install.php after setup.
- You are responsible for funding payouts and complying with the laws that apply to you. Set conversion rates and referral bonuses so payouts stay below your real income.
