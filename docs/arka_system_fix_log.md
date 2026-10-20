# ARKA → `arka_system`: Fix Log (para sa pag-aaral at debugging)

Inilipat ang ARKA mula sa lumang database (`arka`) papunta sa bagong normalized database na **`arka_system`** (gawa sa `arka_capstone_revised.sql`). Nakalista rito kung ano ang binago, saan, at bakit, para may maisagot ka kapag pinag-debug ka sa mock defense.

**Resulta:** pumasa ang lahat ng **291 test** sa SQLite at sa MySQL. Bukas din ang lahat ng **74 na page** para sa Super Admin, Admin at Contractor nang walang error.

---

## 1. Ang unang error at bakit ito nangyari

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'employee_id' in 'field list'
SQL: select count(distinct `employee_id`) from `attendances` ...
```

**Ibig sabihin:** hinahanap ng code ang column na `employee_id` sa `attendances`, pero sa bagong disenyo, **`assignment_id`** na ang nandoon. Ang contractor at client ay nasa `client_assignments` na.

**Aral:** ganitong error ang lalabas sa bawat lugar na hindi pa naiaangkop sa bagong table. Ang ayos ay basahin ang column sa tamang table, hindi idagdag ulit ang lumang column.

---

## 2. Mga pangunahing ideya (ito ang ipaliwanag sa defense)

### 2.1 Shared primary key: `contractors.id = users.id`
- Sa disenyo mo, ang mga record na pag-aari ng contractor (leave, cash advance, devotional, KPI, at iba pa) ay tumuturo sa `contractors`, hindi sa `users`.
- Pareho ang numero ng `contractors.id` at `users.id`. Halimbawa: si user #3 ay contractor #3.
- **Bakit:** karaniwang paraan ito para sa 1:1 na table (tinatawag na *shared primary key*). Ang `contractor_id` ay laging ang id ng user, kaya madali itong gamitin sa buong code.
- **Saan:** `app/Models/Contractor.php` (`ensureFor()`) at `database/migrations/0001_01_01_000000_create_users_table.php`.
- May `contractors` row din ang **Admin**, kasi nagta-time track din ito at may payslip.

### 2.2 `client_assignments` bilang junction
- Ang rates, schedules, time_logs, attendances, attendance_corrections, overtime_requests at payroll ay may **`assignment_id`** na, hindi na `employee_id` + `client_id`.
- **Saan:** `app/Models/ClientAssignment.php` at `app/Models/Concerns/BelongsToAssignment.php`.
- Puwede pa ring gamitin sa code ang `$rate->employee_id` at `$rate->client_id`. Binabasa ng trait ang mga ito mula sa assignment, at kapag in-set mo sila, hinahanap o binubuksan nito ang tamang assignment pagka-save.
- **Pag-query:** `Rate::query()->forEmployee($id)`, `->forClient($id)` at `->forPair($employeeId, $clientId)` ang gamit, hindi na `where('employee_id', ...)`.

### 2.3 Hindi na sine-save ang mga kinukwentang halaga
Ayon sa disenyo mo, kinukwenta na lang ang mga ito kapag kailangan (tulad ng mga view):

| Halaga | Dating lugar | Saan na kinukuha ngayon |
|---|---|---|
| time in/out, break, oras ng attendance | `attendances` | sa `time_logs` ng araw na iyon (`Attendance` model) |
| total_hours, status ng timer | `time_logs` | sa `time_in`, `time_out` at `break_started_at` (`TimeLog` model) |
| overtime, rewards, cash advance, device, net pay | `payroll` | sa `payroll_overtime`, `payroll_rewards`, `cash_advance_repayments`, `device_deductions` (`Payroll` model) |
| hourly rate, daily rate | `payroll` | sa `rates` |
| remaining balance, "repaid" | `cash_advances` | amount − kabuuan ng repayments (`CashAdvance` model) |
| assigned / available | `devices.status` | sa bukas na `device_assignments` (`Device` model) |

Nasa MySQL din ang 5 view (`attendance_summary`, `payroll_summary`, `cash_advance_balances`, `device_current_status`, `conversation_unread_counts`), na pareho ang kwenta.

### 2.4 Mga "trait" (pinagsasaluhang code ng mga model)
Nasa `app/Models/Concerns/`:
- **`BelongsToHomeOffice`:** kusang inilalagay ang `home_office_id` sa mga bagong row (users, clients, leave_types, payroll_periods, devices).
- **`BelongsToContractor`:** para sa mga table na may `contractor_id`. `employee()` ang account; ang `employee_id` ay ibang pangalan ng `contractor_id`.
- **`BelongsToAssignment`:** para sa mga table na may `assignment_id` (tingnan ang 2.2).
- **`PaidInPayroll`:** para sa overtime at rewards. Nasa junction table na kung saang payroll sila nabayaran (`payroll_overtime`, `payroll_rewards`).

---

## 3. Database at migrations

| File | Ano ang ginawa |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | home_offices, users, user_profiles, contractors, password resets, sessions |
| `database/migrations/2026_10_20_100000_...activity_logs_and_notifications...` | activity_logs, notifications |
| `database/migrations/2026_10_20_100100_create_client_tables.php` | clients, client_assignments, rates, work_schedules, schedules, schedule_days, client_assignment_requests |
| `database/migrations/2026_10_20_100200_create_attendance_tables.php` | payroll_periods, leave_types, time_logs, leave_requests, attendances, verifications, corrections, overtime |
| `database/migrations/2026_10_20_100300_create_payroll_tables.php` | payroll, payroll_overtime, payroll_rules, cash_advances, repayments |
| `database/migrations/2026_10_20_100400_create_device_tables.php` | devices, device_assignments, device_deductions |
| `database/migrations/2026_10_20_100500_create_performance_tables.php` | kpi_records, rewards, payroll_rewards, devotionals |
| `database/migrations/2026_10_20_100600_...system_settings_and_chat...` | system_settings, conversations, participants, messages |
| `database/migrations/2026_10_20_100700_add_checks_and_views.php` | 7 CHECK constraint at 5 view (MySQL lang) |

- **Nasubukan na eksaktong kapareho ng `arka_system`:** 46 table, 5 view, 62 foreign key, 7 CHECK at 122 index.
- Ang mga lumang migration ay **inilipat** (hindi binura) sa `database/migrations_old_arka/`.
- Naka-mark na "Ran" ang mga migration sa `arka_system`, kaya hindi na nito uulitin ang paggawa ng mga table.
- **Seeder:** `database/seeders/DatabaseSeeder.php`. Gumagawa ito ng home office, ang 3 account, ang kanilang profile, at ang contractors row ng Admin at Contractor. Patakbuhin gamit ang `php artisan db:seed`.

---

## 4. Mga model

| File | Ano ang binago |
|---|---|
| `User.php` | Binabasa at isinusulat ang birthday, phone, address, emergency contact, photo at profile_completed_at sa `user_profiles`. Ang `employment_type` ay mula na sa bukas na client assignments. Ang rates, schedules, attendances at payrolls ay "through" assignments na. |
| `UserProfile.php` *(bago)* | Mga personal na detalye ng account |
| `Contractor.php` *(bago)* | `ensureFor()`: gumagawa ng row na `id = user_id` |
| `ClientAssignment.php` *(bago)* | `resolve()`, `idsFor()` at `open()` |
| `HomeOffice.php` *(bago)* | `currentId()`: ang home office ng naka-login na user |
| `ScheduleDay.php` *(bago)* | Isang araw ng linggo bawat row, kapalit ng JSON list (1NF) |
| `PayrollOvertime.php`, `PayrollReward.php` *(bago)* | Mga junction table |
| `Schedule.php` | Ang `working_days` ay mula na sa `schedule_days`; may bagong `onWeekday()` |
| `Rate.php` | Ang `working_days` ay ibang pangalan ng `days_per_period`; ang `employment_type` ay sine-save sa assignment |
| `TimeLog.php` | Kinukwenta na ang `status` at `total_hours`; may bagong `withStatus()` |
| `Attendance.php` | Ang time in/out, break at oras ay mula sa time logs. Kapag in-set ang time_in o time_out, ina-adjust nito ang time log. May `withCorrectionCount()` |
| `AttendanceCorrection.php` | Wala nang `attendance_id` (hinahanap sa assignment at date). Ang mga oras ay `DATETIME` na |
| `Payroll.php` | `withAmounts()`: kinukuha ang overtime, rewards, cash advance at device sa isang query. Kinukwenta ang net pay |
| `CashAdvance.php` | May `repayment_months` (2 o 3), `remaining_balance`, `monthly_installment`, `displayStatus()` at `closed()` |
| `Reward.php` | Galing sa KPI ang bawat reward. `cash` o `non_cash` ang `reward_type`. Ang uri (Bonus, Recognition) ay nasa simula ng description (`kind()`, `note()`) |
| `Device.php`, `DeviceAssignment.php` | Kondisyon na lang ang `status`; `end_reason` ang nagsasabi kung paano natapos ang loan |
| `PayrollPeriod.php` | Ang nag-submit (`adminSubmitter`) ay binabasa mula sa activity log; wala nang `admin_submitted_by` |
| `Client.php` | Wala nang `client_code`; ang rates ay "through" assignments |

---

## 5. Mga pagbabago sa patakaran (dahil sa disenyo mo)

1. **Cash advance:** binabayaran na nang **hulugan sa loob ng 2 o 3 buwan**.
   - Hulog bawat payday = halaga ÷ (buwan × payday sa isang buwan). Halimbawa: ₱1,500 sa 2 buwan, semi-monthly: **₱375 bawat payday**.
   - Hindi lalampas ang hulog sa kinita ng payslip na iyon, kaya hindi nagiging negative ang net pay.
   - May pagpili ng **"Repay over: 2 months / 3 months"** sa form ng contractor.
   - **Saan:** `PayrollCalculator::applyCashAdvances()`, `ManageCashAdvance` at `Components/Employee/CashAdvances.jsx`.
2. **Rewards:** galing sa isang **KPI evaluation** ang bawat reward. Hindi na mabubura ang evaluation na may reward. **Saan:** `ManagePerformance`.
3. **Client assignment request para sa kasalukuyang client:** wala nang break allowance na dala (sabi ng CHECK constraint). Para sa bagong client lang ito.
4. **Pagtatapos ng client:** sabay na isinasara ang rate at ang `client_assignments` (`EndClientAssignment`).
5. **Organization name:** ang pangalan ng home office (`home_offices.name`), hindi na setting (`RuleController`).

---

## 6. Mga karaniwang error at paano inayos (para sa debugging)

| Error | Bakit | Ayos |
|---|---|---|
| `Unknown column 'employee_id'` | Nasa `client_assignments` na ang contractor | `->forEmployee($id)` (assignment table) o `where('contractor_id', ...)` (contractor table) |
| `ambiguous column name: end_date` | Ang "through" query ay pinagsasama ang dalawang table na parehong may `start_date` at `end_date` | Pangalanan ang table: `rates.end_date`, o gamitin ang scope na kusang gumagawa nito (`qualifyColumn`) |
| `ambiguous column name: id` sa `with('employee:id,name')` | Pareho ring may `id` ang `client_assignments` | `with('employee:users.id,name')` at `with('client:clients.id,client_name')` |
| `Call to undefined method HasOneThrough::getOwnerKeyName()` | Hindi kaya ng `->for($user, 'employee')` ng factory ang through-relationship | `database/factories/Concerns/ForAssignment.php` |
| `NOT NULL constraint failed: time_logs.assignment_id` | `$user->timeLogs()->create()`: hindi kayang gumawa ng row ang through-relationship | `TimeLog::query()->create(['employee_id' => ..., 'client_id' => ...])` |
| `Incorrect datetime value: '09:00' for column 'original_time_in'` | `DATETIME` na ang column sa bagong disenyo | Isinasama ang petsa ng correction (`AttendanceCorrection::timeOfDay()`) |
| `UNIQUE constraint failed: users.super_admin_home_office_id` | Isang Super Admin lang bawat home office (generated column) | Tama ang database; sa test, may sariling home office ang pangalawang Super Admin |
| `Data truncated for column 'reward_type'` | `cash` o `non_cash` na lang ang tinatanggap | `ManagePerformance::grantReward()` |

**Babala tungkol sa SQLite:** kapag hindi kilala ang column sa double quotes, tinatrato ito ng SQLite bilang text sa halip na mag-error. Kaya pinatakbo rin ang lahat ng test sa MySQL (`arka_testing`).

---

## 7. Paano patakbuhin

```bash
# .env: DB_DATABASE=arka_system
php artisan migrate:status   # dapat "Ran" lahat
php artisan db:seed          # superadmin@arka.co / admin@arka.co / contractor@arka.co, password: password
npm run build
php artisan test             # 320 tests
```

---

## 8. Hindi pa tapos (susunod na gagawin)

- **Chat:** tapos na ang Community Chat (tingnan ang section 12). Wala pang direct at group chat.
- **Multi-home-office:** tapos na (tingnan ang section 9).
- **Data mula sa lumang `arka`:** hindi pa nalilipat. Kung kailangan, gagawa ng script na naglilipat nito.

---

## 9. Register Home Office at paghihiwalay ng data (multi-home-office)

### 9.1 Ano ito (simpleng paliwanag)
Isipin ang isang building na may dalawang opisina na iisa lang ang filing cabinet (ang database). May sticker ang bawat folder kung kanino ito (`home_office_id`). Ang **global scope** ang guwardiya sa harap ng cabinet: tinitingnan nito ang ID ng naka-login, at mga folder lang ng sarili niyang home office ang ibinibigay. Hindi na kailangang baguhin ang bawat query. Kusang idinadagdag ng Laravel ang `WHERE home_office_id = ...`.

**Para sa defense:**
> "Each home office's data is isolated using a global scope on `home_office_id`. Every query is automatically limited to the signed-in user's home office, so one company can never see another company's records, even by typing the link."

### 9.2 Mga file na binago

| Path | Ano ang binago | Ibig sabihin |
|---|---|---|
| `app/Models/HomeOffice.php` | Bagong `scopeId()`, `userIds()`, `within()`, `each()` | `scopeId()`: aling home office ang sinasala (ang sa naka-login). Ginagamit nito ang `Auth::hasUser()`, hindi ang `Auth::user()`, para hindi mag-loop habang nilo-load ang naka-login na user. `within()`/`each()`: para sa mga scheduled command, na walang naka-login. |
| `app/Models/Concerns/BelongsToHomeOffice.php` | Global scope na `home_office` | Sinasala ang `users`, `clients`, `devices`, `payroll_periods` at `leave_types` ayon sa `home_office_id` |
| `app/Models/Concerns/BelongsToContractor.php` | Global scope | Leave, cash advance, devices, devotionals, KPI, verifications: sinasala ayon sa home office ng contractor (`contractor_id`) |
| `app/Models/Concerns/BelongsToAssignment.php` | Global scope | Rates, schedules, time logs, attendance, corrections, overtime, payroll: dumadaan sa `client_assignments` papunta sa contractor |
| `app/Models/ClientAssignment.php` | Global scope + `idsInHomeOffice()` | Ang assignment mismo ay sinasala rin ayon sa contractor |
| `app/Models/Contractor.php`, `Reward.php`, `ActivityLog.php` | Global scope | Contractor ayon sa `user_id`; Reward ayon sa KPI record; Activity log ayon sa gumawa |
| `app/Services/Settings/SystemRules.php`, `MailSettings.php` | Naka-cache bawat home office | Hindi maghahalo ang rules ng dalawang home office kahit sabay na binabasa ng isang command |
| `app/Console/Commands/*` | `HomeOffice::each(...)` | Tumatakbo ang `stop-finished-shifts`, `admin-digest` at `devotional-reminders` nang isa-isa bawat home office, gamit ang sariling rules at Gmail nito |
| Mga controller/request na may `Rule::exists` o `Rule::unique` | `->where('home_office_id', HomeOffice::currentId())` | Diretsong tumitingin sa table ang validation rules (walang global scope), kaya kailangang sabihin ang home office. Halimbawa: hindi mapipili ang contractor ng ibang home office. Per home office na rin ang `serial_number` ng device at ang pangalan ng leave type. |
| `app/Http/Controllers/Auth/RegisteredUserController.php` | Isinulat muli | Gumagawa ng home office + Super Admin (`ARKA-0001`) sa loob ng isang `DB::transaction`, tapos login at diretso sa dashboard. Hindi pwedeng maulit ang pangalan ng home office (kahit iba ang letter case); global unique pa rin ang email. |
| `routes/auth.php` | `throttle:6,1` sa `POST /register` | Hanggang 6 na subok bawat minuto, para hindi magamit sa spam |
| `resources/js/Pages/Auth/Login.jsx` | Register card | "New to ARKA? Register your home office" sa login card; parehong blur card na nagsla-slide down. Ang `/register` ay nagbubukas agad ng register card. |
| `resources/js/Pages/Auth/Register.jsx` | Binura | Lumang Breeze page na hindi na ginagamit |
| `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/HomeOfficeIsolationTest.php` | Bago | Pinapatunayan ang registration at na hindi nakikita ng Home Office B ang data ng A (listahan, link, attendance, form, rules) |

### 9.3 Mga posibleng tanong at error

| Sitwasyon | Bakit | Saan titingnan |
|---|---|---|
| Lumalabas ang "404 Not Found" kapag binuksan ang link ng record | Nasa ibang home office ang record. Tama ang behavior na ito. | Global scope sa trait ng model |
| May kailangang makita ang lahat ng home office (hal. admin tool) | Naka-on ang scope kapag may naka-login | `Model::withoutGlobalScope('home_office')` |
| Sa command, maling home office ang rules | Hindi tumatakbo sa loob ng `HomeOffice::within()` | `app/Console/Commands` |
| "The selected employee id is invalid" kahit tama ang id | Nasa ibang home office ang contractor | Ang `Rule::exists(...)->where('home_office_id', ...)` |

---

## 10. Bagong login UI at "default password" na reset (mula sa disenyo sa PDF)

Kinuha sa branch na `backup/before-revert-2026-10-19` (ginawa na natin ito dati), tapos iniangkop sa `arka_system` at sa home office.

| Path | Ano ang binago | Ibig sabihin |
|---|---|---|
| `resources/js/Pages/Auth/Login.jsx` | Card na may 4 na view: `choose`, `member`, `super`, `register` | Unang makikita ang "Log in as Admin / Contractor" at "Super Admin log in". May "Register your home office" lang sa Super Admin card. May label ang bawat field ng register card, at may Back button. |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Http/Requests/Auth/LoginRequest.php` | `portal=super_admin` | Super Admin lang ang makakapasok sa "Super Admin log in". Ang Admin o Contractor na gumamit nito ay makakakita ng error. |
| `resources/js/Pages/Auth/ForgotPassword.jsx` | "Lost your bearings?" | Super Admin: maglagay ng email at magpapadala ang ARKA ng bagong default password. Contractors at Admins: hihingi sila sa Admin nila. |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | Default password sa halip na reset link | Pareho ang sagot sa kahit anong email, para hindi malaman kung alin ang email ng Super Admin. May 10 minutong cooldown. |
| `app/Actions/Accounts/ResetPassword.php`, `app/Notifications/PasswordWasReset.php` | Bago | Gumagawa ng default password, ini-email ito, nila-logout ang account sa lahat ng device, at pinapagawa ng sariling password sa susunod na login |
| `AccountController::resetPassword`, mga route na `password-reset` (`routes/web.php`, `routes/admin.php`) | Bago | "Reset password" na button sa Workforce at Employees page para sa Admin/Super Admin. Dahil sa global scope, contractor lang ng sariling home office ang mare-reset. |
| `AccountActionDialog.jsx`, `Flash.jsx`, `EmployeeShow.jsx`, `Admin/Employees/Show.jsx` | Reset password dialog | Kapag hindi naipadala ang email, ipapakita nang isang beses ang default password para maibigay nang pribado |
| `routes/auth.php`, `AppServiceProvider.php` | Inalis ang `reset-password/{token}` routes at ang Fortify reset at registration features | Wala nang reset-by-link. Ang sign-up ay ang "Register your home office" lang. |
| `NewPasswordController.php`, `Pages/Auth/ResetPassword.jsx` | Binura | Hindi na ginagamit |
| `tests/Feature/Auth/AuthenticationTest.php`, `PasswordResetTest.php`, `tests/Feature/Workforce/PasswordResetByAdminTest.php` | Mula sa backup | Kabuuan: 312 tests |

---

## 11. Address ng home office

**Database (ikaw ang nagdagdag, gamit ang SQL):**
```sql
ALTER TABLE `home_offices` ADD COLUMN `address` varchar(255) NULL DEFAULT NULL AFTER `name`;
```
Hindi na kailangan ang `php artisan migrate`. Idinagdag ang column sa migration na "Ran" na, kaya hindi ito tatakbo ulit sa `arka_system`. Para lang ito tugma ang code at ang test database.

| Path | Ano ang binago | Ibig sabihin |
|---|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | `$table->string('address')->nullable();` | Tugma sa `ALTER TABLE` na ginawa mo |
| `app/Models/HomeOffice.php` | `address` sa `Fillable` | Pwede nang i-save ang address |
| `app/Http/Controllers/SuperAdmin/RuleController.php` | `homeOfficeRules()` (name at address) | System & Rules → Home Offices: Organization name at Address. Kapag binura ang address, nagiging `NULL` ito. |
| `app/Http/Controllers/Auth/RegisteredUserController.php`, `Login.jsx` | "Address (optional)" | Pwedeng ilagay sa pag-register, o laktawan |
| `app/Http/Middleware/HandleInertiaRequests.php` | Shared prop na `homeOffice` | Alam ng bawat page ang pangalan at address ng home office ng naka-login |
| `resources/js/Components/Payslip/PayslipDetail.jsx` | Header kapag ipi-print | Pangalan at address ng home office sa itaas ng printed payslip |
| `tests/Feature/SuperAdmin/SystemRulesTest.php`, `tests/Feature/Auth/RegistrationTest.php` | Mga test para sa address | Kabuuan: 313 tests |

---

## 12. Community Chat

Isang chat room bawat home office, para sa lahat ng role (Super Admin, Admins, Contractors). Ginagamit nito ang mga table na nasa `arka_system` na: `conversations` (`type = 'community'`), `conversation_participants` at `messages`. Walang binago sa database.

| Path | Ano ito | Ibig sabihin |
|---|---|---|
| `app/Models/Conversation.php` | Bagong model | `communityFor()`: ang community chat ng home office, ginagawa sa unang bukas. `join()`: idinadagdag ang user bilang participant. `markReadBy()`: ina-update ang `last_read_at`. Walang `home_office_id` ang `conversations`, kaya ang global scope ay dumadaan sa `created_by`. |
| `app/Models/Message.php` | Bagong model | Isang mensahe. Dahil sa composite foreign key, kailangang participant muna ang sender bago makapag-send, kaya `join()` muna. Global scope sa home office. |
| `app/Http/Controllers/CommunityChatController.php` | Bagong controller | `index`: huling 200 mensahe. `store`: mag-send (hanggang 2000 letra, 30 bawat minuto). `destroy`: sariling mensahe lang, o kahit alin para sa Super Admin (moderation). |
| `routes/web.php` | `community.index`, `community.messages.store`, `community.messages.destroy` | Para sa lahat ng naka-login at verified |
| `app/Support/Navigation.php`, `resources/js/Components/Icons.jsx` | "Community Chat" sa sidebar | Lumalabas sa bawat role at view |
| `resources/js/Pages/Community/Index.jsx` | Bagong page | Chat bubbles (teal ang sa iyo), may pangalan at role, hiwalay ayon sa araw. Enter para mag-send, Shift+Enter para sa bagong linya. Nagre-refresh bawat 4 segundo (`usePoll`), kaya hindi kailangan ng websocket server. |
| `tests/Feature/CommunityChatTest.php` | 6 na test | Lahat ng role ay makakapag-post; bawal ang walang laman; hiwalay ang chat ng bawat home office; sino ang makakapag-delete |

**Para sa defense:** "The chat uses polling every 4 seconds through Inertia's `usePoll`. It is simpler than websockets and enough for a small team. Messages are isolated per home office by the same global scope as the rest of ARKA."
