// Lucide icons, outline only, stroke-width 1.5 (ARKA brand guide). One icon set across the product.
import {
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    Banknote,
    BarChart3,
    Bell,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Coffee,
    Flag,
    KeyRound,
    List,
    LockKeyhole,
    Monitor,
    Moon,
    Pause,
    Play,
    Plus,
    CircleStop,
    Sun,
    Check,
    ClipboardCheck,
    Clock,
    Download,
    Eye,
    EyeOff,
    FileText,
    History,
    LayoutGrid,
    LogOut,
    Menu,
    PanelLeft,
    Printer,
    Receipt,
    SlidersHorizontal,
    Trophy,
    Upload,
    UserRound,
    Users,
    Wallet,
    X,
} from 'lucide-react';

function brand(IconComponent) {
    const Wrapped = ({ className = 'h-[18px] w-[18px]', ...props }) => (
        <IconComponent strokeWidth={1.5} aria-hidden="true" className={className} {...props} />
    );
    Wrapped.displayName = IconComponent.displayName;

    return Wrapped;
}

export const DashboardIcon = brand(LayoutGrid);
export const UsersIcon = brand(Users);
export const WalletIcon = brand(Wallet);
export const ReceiptIcon = brand(Receipt);
export const ClipboardCheckIcon = brand(ClipboardCheck);
export const BanknoteIcon = brand(Banknote);
export const TrophyIcon = brand(Trophy);
export const SlidersIcon = brand(SlidersHorizontal);
export const ChartIcon = brand(BarChart3);
export const BellIcon = brand(Bell);
export const HistoryIcon = brand(History);
export const UserIcon = brand(UserRound);
export const LogoutIcon = brand(LogOut);
export const PanelIcon = brand(PanelLeft);
export const CheckIcon = brand(Check);
export const ArrowRightIcon = brand(ArrowRight);
export const ArrowLeftIcon = brand(ArrowLeft);
export const MenuIcon = brand(Menu);
export const CloseIcon = brand(X);
export const CalendarClockIcon = brand(CalendarClock);
export const CalendarCheckIcon = brand(CalendarCheck);
export const CalendarDaysIcon = brand(CalendarDays);
export const BookIcon = brand(BookOpen);
export const ClockIcon = brand(Clock);
export const FileTextIcon = brand(FileText);
export const UploadIcon = brand(Upload);
export const EyeIcon = brand(Eye);
export const EyeOffIcon = brand(EyeOff);
export const DownloadIcon = brand(Download);
export const PrinterIcon = brand(Printer);
export const SwitchIcon = brand(ArrowLeftRight);
export const SunIcon = brand(Sun);
export const MoonIcon = brand(Moon);
export const MonitorIcon = brand(Monitor);
export const PlayIcon = brand(Play);
export const PauseIcon = brand(Pause);
export const StopIcon = brand(CircleStop);
export const CoffeeIcon = brand(Coffee);
export const FlagIcon = brand(Flag);
export const PlusIcon = brand(Plus);
export const CircleCheckIcon = brand(CircleCheck);
export const KeyIcon = brand(KeyRound);
export const ListIcon = brand(List);
export const LockIcon = brand(LockKeyhole);
export const ChevronLeftIcon = brand(ChevronLeft);
export const ChevronRightIcon = brand(ChevronRight);

/** Sidebar icon for each navigation key, across all roles. */
export const moduleIcons = {
    // Super Admin
    dashboard: DashboardIcon,
    workforce: UsersIcon,
    payroll: WalletIcon,
    payslips: ReceiptIcon,
    requests: ClipboardCheckIcon,
    'cash-advances': BanknoteIcon,
    performance: TrophyIcon,
    rules: SlidersIcon,
    reports: ChartIcon,
    notifications: BellIcon,
    'activity-logs': HistoryIcon,
    profile: UserIcon,
    // Admin
    employees: UsersIcon,
    scheduling: CalendarClockIcon,
    attendance: CalendarCheckIcon,
    verification: ClipboardCheckIcon,
    devotionals: BookIcon,
    // Contractor (Contractor flow, "Sidebar Navigation")
    'time-tracker': ClockIcon,
    'time-history': HistoryIcon,
    devotional: BookIcon,
    'my-attendance': CalendarDaysIcon,
    leave: FileTextIcon,
    overtime: ClockIcon,
    payslip: ReceiptIcon,
    'cash-advance': BanknoteIcon,
};

/** Icon for each notification category. */
export const notificationIcons = {
    account: KeyIcon,
    schedule: CalendarClockIcon,
    attendance: CalendarCheckIcon,
    leave: FileTextIcon,
    devotional: BookIcon,
    payslip: ReceiptIcon,
    workforce: UsersIcon,
    announcement: BellIcon,
};
