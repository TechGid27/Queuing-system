# Implementation Notes — Queue System Upgrade

## ✅ Completed Tasks

### 1. Fixed All Potential Issues

#### 1.1 Duplicate Queue Prevention
- **Problem:** Students could join the queue multiple times
- **Fix:** Added validation in `StoreQueueRequest` to check for active tickets (`waiting` or `serving` status)
- **Location:** `app/Http/Requests/StoreQueueRequest.php`

#### 1.2 Staff Registration Security
- **Problem:** Anyone could register as staff
- **Fix:** Added invite code requirement stored in `settings` table
- **Default Code:** `ACLC-STAFF-2026` (can be changed in database)
- **Location:** `AuthController@registerStaff`, `StaffRegisterRequest`

#### 1.3 Cache Fallback
- **Problem:** If cache is cleared, "Now Serving" shows `--` even if someone is being served
- **Fix:** Added DB fallback in `QueueController@index` — checks `serving` status if cache is empty
- **Location:** `app/Http/Controllers/QueueController.php`

#### 1.4 Redundant Purpose Column
- **Status:** Left as-is (denormalized for performance)
- **Reason:** Prevents N+1 queries on queue display; purpose name is frozen at ticket creation time

#### 1.5 Staff Password Confirmation
- **Problem:** `StaffRegisterRequest` didn't require `confirmed` rule
- **Fix:** Added `confirmed` rule to password validation
- **Location:** `app/Http/Requests/StaffRegisterRequest.php`

#### 1.6 `/student/index` Auth Protection
- **Problem:** Route was publicly accessible
- **Fix:** Moved route inside `auth` + `is_student` middleware group
- **Location:** `routes/web.php`

#### 1.7 `callNext` Bulk Update Issue
- **Problem:** `QueueEntry::where('status', 'serving')->update(...)` could affect multiple rows
- **Fix:** Changed to single-record update using `->first()->update(...)`
- **Location:** `app/Http/Controllers/StaffController.php`

---

### 2. Real-Time WebSocket Broadcasting (Pusher)

#### 2.1 Event Broadcasting
- **Created:** `QueueUpdated` event (`app/Events/QueueUpdated.php`)
- **Broadcasts:** Current number, next number, waiting count
- **Channel:** Public channel `queue`
- **Event Name:** `queue.updated`

#### 2.2 Trigger Points
Broadcasting happens on:
- Staff calls next student (`StaffController@callNext`)
- Staff completes a student (`StaffController@complete`)
- Staff skips a student (`StaffController@reject`)
- Student joins queue (`QueueController@store`)
- Auto-skip command runs (`AutoSkipQueue`)

#### 2.3 Frontend Integration
- **Library:** Pusher JS 8.2.0 (loaded via CDN in `layouts/app.blade.php`)
- **Fallback:** If Pusher not configured, falls back to 5-second polling
- **Location:** `resources/views/student/index.blade.php` (scripts section)

#### 2.4 Configuration
Add to `.env`:
```env
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=ap1
```

**Get Pusher credentials:**
1. Sign up at [pusher.com](https://pusher.com) (free tier: 200k messages/day)
2. Create a new Channels app
3. Copy credentials to `.env`

---

### 3. Phone OTP Verification (Semaphore SMS)

#### 3.1 Registration Flow
1. Student fills registration form
2. Account created but `phone_verified_at` is `NULL`
3. OTP sent via SMS (6 digits, valid 10 minutes)
4. Student redirected to `/student/verify-otp`
5. After verification, `phone_verified_at` is set
6. Unverified students are blocked from login

#### 3.2 OTP Features
- **Expiry:** 10 minutes
- **Resend:** Rate-limited (3 attempts per 2 minutes)
- **Storage:** `phone_otps` table
- **Cleanup:** Old OTPs deleted after verification

#### 3.3 SMS Integration (Textbee)
- **Provider:** [Textbee.dev](https://textbee.dev) — uses your Android phone as the SMS gateway
- **Cost:** Free (uses your existing SIM plan)
- **Fallback:** If `TEXTBEE_API_KEY` or `TEXTBEE_DEVICE_ID` is not set, OTP is logged (for local dev)
- **Phone format:** Auto-converts `09XXXXXXXXX` → `+639XXXXXXXXX` (E.164)

#### 3.4 Configuration
Add to `.env`:
```env
TEXTBEE_API_KEY=your_api_key_here
TEXTBEE_DEVICE_ID=your_device_id_here
```

**Get Textbee credentials:**
1. Sign up at [textbee.dev](https://textbee.dev)
2. Install the **Textbee** app on your Android phone
3. Register your device in the dashboard
4. Copy your **Device ID** and generate an **API Key**

#### 3.5 Files Created
- `app/Models/PhoneOtp.php`
- `database/migrations/2026_04_20_065649_create_phone_otp_table.php`
- `database/migrations/2026_04_20_065738_add_phone_verified_to_users_table.php`
- `resources/views/auth/verify-otp.blade.php`

#### 3.6 Routes Added
```php
GET  /student/verify-otp      → showVerifyOtp
POST /student/verify-otp      → verifyOtp
POST /student/resend-otp      → resendOtp
```

---

## 📋 Database Changes

### New Tables
1. **`phone_otps`**
   - `phone_number` (indexed)
   - `otp` (6 digits)
   - `expires_at`

2. **`settings`**
   - `key` (unique)
   - `value`
   - Seeded with `staff_invite_code = ACLC-STAFF-2026`

### Modified Tables
1. **`users`**
   - Added `phone_verified_at` (timestamp, nullable)

---

## 🚀 Deployment Checklist

### 1. Environment Setup
```bash
# Copy .env.example to .env
cp .env.example .env

# Update these keys in .env:
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1

SEMAPHORE_API_KEY=
SEMAPHORE_SENDER_NAME=ACLCQUEUE
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Seed Purposes (Optional)
```bash
php artisan db:seed --class=PurposeSeeder
```

### 4. Test OTP (Local Dev)
If `SEMAPHORE_API_KEY` is not set, OTP will be logged:
```bash
tail -f storage/logs/laravel.log
```

### 5. Test Broadcasting (Local Dev)
Without Pusher, the system falls back to polling. To test real-time:
1. Set up Pusher credentials
2. Open two browser windows (student view + staff dashboard)
3. Call next student → both should update instantly

---

## 🔐 Security Notes

### Staff Invite Code
- Default: `ACLC-STAFF-2026`
- Change it in the database:
```sql
UPDATE settings SET value = 'YOUR-NEW-CODE' WHERE key = 'staff_invite_code';
```

### OTP Rate Limiting
- **Login:** 5 attempts per minute per IP
- **OTP Resend:** 3 attempts per 2 minutes per phone number

### Phone Verification Bypass (Dev Only)
To skip OTP in development, manually set `phone_verified_at`:
```sql
UPDATE users SET phone_verified_at = NOW() WHERE email = 'test@example.com';
```

---

## 📱 SMS Testing (Without Credits)

For local testing without spending on SMS:
1. Leave `SEMAPHORE_API_KEY` blank in `.env`
2. Register a student
3. Check `storage/logs/laravel.log` for the OTP
4. Enter it manually on the verification page

---

## 🐛 Troubleshooting

### Broadcasting Not Working
1. Check `.env` has `BROADCAST_DRIVER=pusher`
2. Verify Pusher credentials are correct
3. Check browser console for Pusher connection errors
4. Fallback polling should still work (5-second refresh)

### OTP Not Sending
1. Check `SEMAPHORE_API_KEY` is set
2. Verify Semaphore account has credits
3. Check `storage/logs/laravel.log` for errors
4. Test with a valid PH mobile number (`09XXXXXXXXX`)

### Staff Can't Register
1. Check `settings` table has `staff_invite_code` row
2. Verify the invite code matches what you're entering
3. Check for validation errors in the form

---

## 📊 Performance Notes

- **Broadcasting:** Pusher free tier supports 200k messages/day (more than enough for a school)
- **SMS:** Semaphore charges ~₱0.50/SMS; budget accordingly
- **Cache:** Queue state is cached forever; cleared only on updates
- **Polling Fallback:** 5-second interval (low server load)

---

## 🎯 Next Steps (Optional Enhancements)

1. **Email Verification:** Add email OTP alongside phone OTP
2. **Admin Panel for Settings:** UI to change staff invite code
3. **SMS Templates:** Customize OTP message format
4. **Multi-language Support:** Cebuano/Tagalog translations
5. **Queue Analytics:** Charts for daily traffic patterns
6. **Mobile App:** React Native app with push notifications

---

## 📞 Support

For issues or questions:
- Check `storage/logs/laravel.log` for errors
- Verify all migrations ran: `php artisan migrate:status`
- Test routes: `php artisan route:list`

---

**Implementation Date:** April 20, 2026  
**Laravel Version:** 9.x  
**PHP Version:** 8.0.30
