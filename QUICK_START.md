# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Update Environment
```bash
# Copy the example if you haven't already
cp .env.example .env

# Edit .env and add these lines (or update existing ones):
```

```env
# Broadcasting (Real-time Updates)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=ap1

# SMS OTP (Phone Verification)
TEXTBEE_API_KEY=your_textbee_api_key
TEXTBEE_DEVICE_ID=your_textbee_device_id
```

### Step 2: Run Migrations
```bash
php artisan migrate
```

### Step 3: Seed Sample Data (Optional)
```bash
php artisan db:seed --class=PurposeSeeder
```

### Step 4: Start the Server
```bash
php artisan serve
```

Visit: `http://localhost:8000`

---

## 🔑 Get Your API Keys

### Pusher (Real-time Broadcasting)
1. Go to [pusher.com](https://pusher.com)
2. Sign up (free tier: 200k messages/day)
3. Create a new **Channels** app
4. Copy: App ID, Key, Secret, Cluster
5. Paste into `.env`

**Without Pusher:** System falls back to 5-second polling (still works!)

### Textbee (SMS OTP)
1. Go to [textbee.dev](https://textbee.dev)
2. Sign up and install the Android app on your phone
3. Register your device in the dashboard
4. Copy your **Device ID** and generate an **API Key**
5. Paste into `.env`:
```env
TEXTBEE_API_KEY=your_api_key_here
TEXTBEE_DEVICE_ID=your_device_id_here
```

**Without Textbee:** OTP will be logged to `storage/logs/laravel.log` (dev mode)

---

## 🧪 Test the System

### Test 1: Student Registration with OTP
1. Go to `/student/register`
2. Fill the form (use a real PH number: `09XXXXXXXXX`)
3. Check your phone for OTP (or check logs if no API key)
4. Enter OTP on verification page
5. Login and join the queue

### Test 2: Staff Registration
1. Go to `/staff/register`
2. Use invite code: `ACLC-STAFF-2026`
3. Login and access admin dashboard

### Test 3: Real-time Updates
1. Open two browser windows:
   - Window 1: Student view (`/student/index`)
   - Window 2: Staff dashboard (`/admin`)
2. In staff dashboard, click "Call Next"
3. Watch student view update **instantly** (if Pusher is configured)

---

## 🔧 Change Staff Invite Code

```sql
-- Connect to your database and run:
UPDATE settings SET value = 'YOUR-NEW-CODE' WHERE key = 'staff_invite_code';
```

Or use a database client (phpMyAdmin, TablePlus, etc.)

---

## 📱 Local Testing (No SMS Credits)

1. Leave `SEMAPHORE_API_KEY` blank in `.env`
2. Register a student
3. Open terminal and run:
   ```bash
   tail -f storage/logs/laravel.log
   ```
4. Look for: `OTP SMS to 09XXXXXXXXX: Your ACLC Queue System OTP is: 123456`
5. Copy the OTP and paste it on the verification page

---

## ✅ Verify Everything Works

```bash
# Check migrations ran
php artisan migrate:status

# Check routes loaded
php artisan route:list | grep student

# Check for errors
tail -f storage/logs/laravel.log
```

---

## 🎉 You're Done!

The system now has:
- ✅ Real-time queue updates (WebSocket)
- ✅ Phone OTP verification
- ✅ Staff invite code protection
- ✅ Duplicate queue prevention
- ✅ All security fixes applied

**Default Credentials:**
- Staff Invite Code: `ACLC-STAFF-2026`
- No default users (register your own)

---

## 🆘 Need Help?

Check `IMPLEMENTATION_NOTES.md` for detailed documentation.

**Common Issues:**
- **OTP not sending?** Check Semaphore credits and API key
- **Real-time not working?** Check Pusher credentials (polling fallback still works)
- **Can't register staff?** Use invite code `ACLC-STAFF-2026`
