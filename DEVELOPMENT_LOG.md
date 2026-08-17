# GTournament1 Development Log

วันที่บันทึก: 17 สิงหาคม 2026  
Repository: `guyttzx-del/GTournament1`  
Branch ปัจจุบัน: `main`

## 1. จุดเริ่มต้น

โปรเจกต์เริ่มจาก PHP prototype หน้าเดียวที่ใช้ข้อมูลจำลองใน `index.php` โดยมีหน้า Dashboard, Tournaments, Rules, Rankings และ Registration พร้อม CSS/JavaScript สำหรับ public UI

ตรวจ repository จาก GitHub แล้วพบว่า:

- default branch คือ `main`
- commit ล่าสุดเดิมคือ `Initial GTournament1 web prototype`
- ไม่มี open issue หรือ pull request
- ยังไม่มี Supabase project จริงใน environment

## 2. การปรับปรุง UI และ accessibility

- เปลี่ยน mobile menu จาก inline style เป็น class-based state
- เพิ่ม `aria-controls`, `aria-expanded`, keyboard Escape handling และ focus states
- เพิ่มหน้า Auth ใหม่ให้มี Login/Sign up cards, account benefits และ responsive layout
- เพิ่มหน้า Privacy, Terms และ Contact
- ทำ Tournament status filter และ Ranking scope filter ให้ใช้ query parameters
- เพิ่ม alert styles และ file input styles
- จัด format `assets/style.css` จากไฟล์บีบเป็นบรรทัดเดียวให้เป็นโครงสร้างอ่านง่าย โดยคง selector และ cascade เดิม

## 3. Foundation สำหรับระบบจริง

เพิ่มโครงสร้าง:

- `.env.example` สำหรับ Supabase URL/key, session และ private bucket
- `config/config.php` สำหรับ environment loading, secure session และ security headers
- `src/Application.php` สำหรับ application bootstrap, Supabase client และ authorization helpers
- `src/Infrastructure/SupabaseClient.php` สำหรับ REST/Auth/Storage upload และ signed URL
- `src/Support/Csrf.php` และ `src/Support/Flash.php`
- `src/Domain/RegistrationService.php` สำหรับ validation, duplicate protection, slip upload และ audit log
- `src/Domain/RegistrationStatus.php` สำหรับ status transition และ localized labels

## 4. Database และ security

สร้าง migrations:

- `database/migrations/001_initial.sql`
  - profiles, staff_roles, seasons, registrations, payments, audit_logs
  - registration statuses
  - RLS policies
  - private `slips` bucket
  - season capacity trigger
- `database/migrations/002_competition_and_payments.sql`
  - PromptPay fields ต่อ Season
  - groups, group_members, matches, match_results
  - match_evidence และ brackets
  - match status/stage enums
  - RLS policies สำหรับ players และ Staff

Security controls ที่เพิ่ม:

- CSRF validation ใน POST flows
- secure HttpOnly/SameSite session cookie
- CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy และ Permissions-Policy
- private Storage paths ที่แยกตาม user
- signed URL สำหรับ Staff เปิดสลิปแบบอายุสั้น
- server-side MIME/size validation สำหรับไฟล์สลิป
- status transition validation และบังคับเหตุผลเมื่อ reject
- `.env` และ runtime session/log files ไม่ถูก commit

## 5. ระบบการแข่งขันและอันดับ

- `src/Domain/CompetitionService.php`
  - ตรวจว่ามีผู้เล่น 32 คน
  - สุ่มเป็น 8 กลุ่ม กลุ่มละ 4 คน
  - สร้าง fixtures แบบพบกันหมด 6 นัดต่อกลุ่ม รวม 48 นัด
- `src/Domain/RankingService.php`
  - W=3, D=1, L=0
  - played, wins, draws, losses, goals for/against, goal difference และ rank
  - เรียงคะแนน → ผลต่างประตู → ประตูได้ → registration id เป็น deterministic fallback

## 6. Tests และการตรวจสอบ

- PHP lint ผ่านทุกไฟล์ PHP
- JavaScript syntax check ผ่าน
- `git diff --check` ผ่าน
- `tests/smoke.ps1` ตรวจ 14 routes และ 3 assets
- `tests/domain.php` ตรวจ ranking calculation, status transitions และ group/fixture generation
- Smoke tests ล่าสุด: `14 routes + 3 assets` ผ่าน
- Domain tests ล่าสุด: ผ่าน

## 7. สิ่งที่ยังต้องทำ

- สร้าง Supabase project จริงและตั้งค่า Auth/SMTP/Storage
- รัน migrations และ seed ใน Supabase
- เพิ่ม Admin UI สำหรับ Season และ PromptPay settings
- เชื่อม CompetitionService กับ Supabase เพื่อสร้าง groups/fixtures จริง
- เพิ่ม Match result submission, confirmation และ dispute UI/API
- เชื่อม RankingService กับ match results จริง
- ทำ authenticated end-to-end tests
- backup/restore drill และ UAT ก่อนเปิดรับเงินจริง

## 8. สถานะการเผยแพร่

การเปลี่ยนแปลงทั้งหมดใน working tree มาจากการพัฒนาตามแผนนี้ และยังไม่ได้ commit/push ณ เวลาบันทึกนี้

ขั้นตอน publish ที่รอทำ:

1. ตรวจ `gh auth status`
2. สร้าง branch publish ตาม workflow
3. stage เฉพาะไฟล์ใน scope
4. commit พร้อมข้อความสรุป
5. push ไป `origin`
6. เปิด draft pull request พร้อม test results และ known limitations
