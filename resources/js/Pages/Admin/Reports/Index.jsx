import Panel, { PanelHeading } from '@/Components/Console/Panel';
import {
    ArrowRightIcon,
    BanknoteIcon,
    BookIcon,
    CalendarCheckIcon,
    CalendarClockIcon,
    FileTextIcon,
    ReceiptIcon,
    TrophyIcon,
    UsersIcon,
    WalletIcon,
} from '@/Components/Icons';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';

const details = {
    attendance: {
        icon: CalendarCheckIcon,
        text: 'Present, late, absent, leave and incomplete days per contractor, with a day-by-day detail table.',
    },
    leave: {
        icon: FileTextIcon,
        text: 'Paid and unpaid leave per employee. Leave is approved by the Super Admin; this is view-only.',
    },
    devotional: {
        icon: BookIcon,
        text: 'Submitted, late and missing devotionals per employee. Compliance only — never a payroll deduction.',
    },
    workforce: {
        icon: UsersIcon,
        text: 'Admins and contractors by role, Full-Time / Part-Time classification, status and client assignments.',
    },
    schedule: {
        icon: CalendarClockIcon,
        text: 'Schedules that apply in the period: client, position, working days, shift times and weekly hours.',
    },
    payroll: {
        icon: WalletIcon,
        text: 'Gross pay, additions, approved deductions and net pay per contractor for the pay periods in range.',
    },
    payslip: {
        icon: ReceiptIcon,
        text: 'Released payslips per period and per contractor, combining every client they served.',
    },
    deduction: {
        icon: FileTextIcon,
        text: 'Absence, late, cash advance, device and other deductions. Tithes and devotional penalties never appear.',
    },
    'cash-advance': {
        icon: BanknoteIcon,
        text: 'Requests, released amounts, repayments and the balance still outstanding.',
    },
    performance: {
        icon: TrophyIcon,
        text: 'KPI evaluations, average scores and the rewards or incentives granted.',
    },
};

export default function Index({ reports, routes = { index: 'admin.reports.index', show: 'admin.reports.show' } }) {
    return (
        <AppLayout title="Overview" eyebrow="Reports">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <p className="text-console-muted">Reports read records from the other modules and are never edited here. Generate one, then print to PDF or export CSV.</p>
                <div className="grid gap-9 md:grid-cols-2 md:gap-7 xl:grid-cols-3">
                    {reports.map((report) => {
                        const Icon = details[report.key]?.icon ?? FileTextIcon;

                        return (
                            <Panel key={report.key}>
                                <Icon className="h-6 w-6 text-arka-teal" />
                                <div className="mt-4">
                                    <PanelHeading title={report.title} subtitle={details[report.key]?.text} />
                                </div>
                                <Link
                                    href={route(routes.show, report.key)}
                                    className="group mt-6 inline-flex items-center gap-2 border border-arka-teal bg-arka-teal px-4 py-2 text-sm font-medium text-white hover:bg-[#276E82]"
                                >
                                    Generate report
                                    <ArrowRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                                </Link>
                            </Panel>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
