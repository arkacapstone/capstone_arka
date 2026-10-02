import CashAdvanceList from '@/Components/Employee/CashAdvances';
import AppLayout from '@/Layouts/AppLayout';

/** Contractor → Cash Advance: request one, follow the decision and the balance repaid through payroll. */
export default function CashAdvances({ advances, rules }) {
    return (
        <AppLayout title="Cash advances" eyebrow="Cash advance">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <CashAdvanceList advances={advances} rules={rules} />
            </div>
        </AppLayout>
    );
}
