import Dialog from '@/Components/Console/Dialog';
import Field, { ConsoleButton, SecondaryButton, SelectField, TextAreaField } from '@/Components/Console/Field';
import Panel, { Eyebrow, MetricRow, PanelHeading } from '@/Components/Console/Panel';
import { Tag } from '@/Components/Console/StatusBadge';
import { PlusIcon, TrophyIcon } from '@/Components/Icons';
import ConfirmDialog from '@/Components/Workforce/ConfirmDialog';
import Table, { Cell, Row } from '@/Components/Workforce/Table';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { fullDate, peso, timeAgo } from '@/lib/format';
import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const ratingTone = { Excellent: 'live', 'Very good': 'live', Good: 'waiting', 'Needs support': 'closed' };

function Figure({ label, value }) {
    return (
        <div className="border border-console-line px-5 py-4">
            <Eyebrow>{label}</Eyebrow>
            <p className="mt-2 font-mono text-2xl font-medium text-console-heading">{value}</p>
        </div>
    );
}

const percent = (value) => (value === null || value === undefined ? '—' : `${value}%`);

function EvaluateDialog({ open, onClose, employees, periods, suggestion }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        employee_id: suggestion?.employeeId ?? '',
        period_id: suggestion?.periodId ?? '',
        kpi_score: '',
        remarks: '',
    });

    // A new suggestion arrives when the contractor or period changes.
    useEffect(() => {
        if (suggestion?.suggested !== null && suggestion?.suggested !== undefined && data.kpi_score === '') {
            setData('kpi_score', String(suggestion.suggested));
        }
    }, [suggestion]); // eslint-disable-line react-hooks/exhaustive-deps

    const suggest = (next) => {
        const merged = { ...data, ...next };
        setData({ ...merged, kpi_score: '' });

        if (merged.employee_id) {
            router.get(
                route('super-admin.performance'),
                { employee: merged.employee_id, ...(merged.period_id ? { period: merged.period_id } : {}) },
                { only: ['suggestion'], preserveState: true, preserveScroll: true, replace: true },
            );
        }
    };

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const current = suggestion && String(suggestion.employeeId) === String(data.employee_id) && String(suggestion.periodId ?? '') === String(data.period_id) ? suggestion : null;

    return (
        <Dialog open={open} onClose={close} side title="KPI evaluation" description="Saving again for the same contractor and period updates the evaluation.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('super-admin.performance.evaluate'), { preserveScroll: true, onSuccess: close });
                }}
                className="flex flex-col gap-5"
            >
                <SelectField
                    id="employee_id"
                    label="Contractor"
                    options={employees}
                    placeholder="Choose a contractor"
                    value={data.employee_id}
                    onChange={(e) => suggest({ employee_id: e.target.value })}
                    error={errors.employee_id}
                    required
                />
                <SelectField
                    id="period_id"
                    label="Pay period"
                    options={periods}
                    placeholder="General (this month so far)"
                    value={data.period_id}
                    onChange={(e) => suggest({ period_id: e.target.value })}
                    error={errors.period_id}
                />

                {current && (
                    <div className="border border-console-line px-4 py-3">
                        <Eyebrow>
                            From records · {fullDate(current.from)} – {fullDate(current.to)}
                        </Eyebrow>
                        <div className="mt-2">
                            <MetricRow label={`Attendance (40%) · ${current.daysPresent} present, ${current.daysAbsent} absent`} value={percent(current.attendance)} />
                            <MetricRow label={`Punctuality (30%) · ${current.daysLate} late`} value={percent(current.punctuality)} />
                            <MetricRow label={`Devotional (30%) · ${current.devotionals} of ${current.days} days`} value={percent(current.devotional)} />
                            <MetricRow label="Suggested score" value={current.suggested ?? '—'} />
                        </div>
                        <p className="mt-2 text-xs text-console-dim">A suggestion only — you decide the final score.</p>
                    </div>
                )}

                <Field
                    id="kpi_score"
                    label="KPI score (0–100)"
                    type="number"
                    min="0"
                    max="100"
                    step="0.1"
                    value={data.kpi_score}
                    onChange={(e) => setData('kpi_score', e.target.value)}
                    error={errors.kpi_score}
                    required
                />
                <TextAreaField id="remarks" label="Remarks (optional)" value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} error={errors.remarks} />
                <ConsoleButton type="submit" disabled={processing}>
                    Save evaluation
                </ConsoleButton>
            </form>
        </Dialog>
    );
}

function RewardDialog({ open, onClose, employees, rewardTypes, kpis, preset }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        employee_id: preset?.employeeId ?? '',
        reward_type: rewardTypes[0]?.value ?? '',
        amount: '',
        description: '',
        kpi_id: preset?.kpiId ?? '',
    });

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const evaluations = kpis
        .filter((kpi) => String(kpi.employee.id) === String(data.employee_id))
        .map((kpi) => ({ value: kpi.id, label: `${kpi.period} · ${kpi.score} (${kpi.rating})` }));

    return (
        <Dialog open={open} onClose={close} side title="Grant a reward" description="The contractor is notified. Monetary incentives are recorded here; they are not added to payroll automatically.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('super-admin.performance.reward'), { preserveScroll: true, onSuccess: close });
                }}
                className="flex flex-col gap-5"
            >
                <SelectField
                    id="reward_employee_id"
                    label="Contractor"
                    options={employees}
                    placeholder="Choose a contractor"
                    value={data.employee_id}
                    onChange={(e) => setData({ ...data, employee_id: e.target.value, kpi_id: '' })}
                    error={errors.employee_id}
                    required
                />
                <SelectField
                    id="reward_type"
                    label="Reward type"
                    options={rewardTypes}
                    value={data.reward_type}
                    onChange={(e) => setData('reward_type', e.target.value)}
                    error={errors.reward_type}
                    required
                />
                <Field
                    id="reward_amount"
                    label="Amount (₱, optional)"
                    type="number"
                    min="0"
                    step="0.01"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    error={errors.amount}
                />
                <SelectField
                    id="kpi_id"
                    label="Linked evaluation (optional)"
                    options={evaluations}
                    placeholder={evaluations.length ? 'Not linked' : 'No evaluations for this contractor'}
                    value={data.kpi_id}
                    onChange={(e) => setData('kpi_id', e.target.value)}
                    error={errors.kpi_id}
                />
                <TextAreaField
                    id="description"
                    label="Description (optional)"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    error={errors.description}
                />
                <ConsoleButton type="submit" disabled={processing}>
                    Grant reward
                </ConsoleButton>
            </form>
        </Dialog>
    );
}

export default function Index({ tab, kpis, rewards, summary, suggestion, employees, periods, rewardTypes }) {
    const [evaluating, setEvaluating] = useState(suggestion !== null);
    const [rewarding, setRewarding] = useState(null); // null | {employeeId?, kpiId?}
    const [removing, setRemoving] = useState(null);
    const [processing, setProcessing] = useState(false);

    const remove = () =>
        router.delete(route('super-admin.performance.evaluations.destroy', removing.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRemoving(null);
            },
        });

    return (
        <SuperAdminLayout title="Performance & Rewards">
            <div className="mx-auto flex max-w-[1560px] flex-col gap-8">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Figure label="Evaluations on record" value={summary.evaluations} />
                    <Figure label="Average KPI score" value={summary.averageScore ?? '—'} />
                    <Figure label="Rewards this month" value={summary.rewardsThisMonth} />
                    <Figure label="Incentives this month" value={peso(summary.incentivesThisMonth)} />
                </div>

                <Panel>
                    <PanelHeading
                        title="Performance & rewards"
                        subtitle="Approved KPI evaluations and the rewards or recognition that follow. Devotional compliance may count as a positive factor — never a deduction."
                        action={
                            <div className="flex gap-2">
                                <SecondaryButton onClick={() => setRewarding({})}>
                                    <TrophyIcon className="h-4 w-4" /> Grant reward
                                </SecondaryButton>
                                <ConsoleButton onClick={() => setEvaluating(true)}>
                                    <PlusIcon className="h-4 w-4" /> New evaluation
                                </ConsoleButton>
                            </div>
                        }
                    />

                    <div className="mt-6 flex gap-1 border-b border-console-line">
                        {[
                            ['kpi', `KPI evaluations (${kpis.length})`],
                            ['rewards', `Reward history (${rewards.length})`],
                        ].map(([value, label]) => (
                            <Link
                                key={value}
                                href={route('super-admin.performance', value === 'kpi' ? {} : { tab: value })}
                                preserveScroll
                                className={`-mb-px border-b-2 px-4 py-2 font-condensed text-[15px] font-semibold transition-colors ${
                                    tab === value ? 'border-arka-teal text-arka-teal' : 'border-transparent text-console-muted hover:text-arka-teal'
                                }`}
                            >
                                {label}
                            </Link>
                        ))}
                    </div>

                    <div className="mt-2">
                        {tab === 'kpi' ? (
                            <Table columns={['Contractor', 'Period', 'Score', 'Result', 'Remarks', 'Evaluated', 'Actions']} isEmpty={kpis.length === 0} emptyMessage="No KPI evaluations yet." minWidth={980}>
                                {kpis.map((kpi) => (
                                    <Row key={kpi.id}>
                                        <Cell>
                                            <p className="font-medium text-console-heading">{kpi.employee.name}</p>
                                            <p className="font-mono text-xs text-console-dim">{kpi.employee.code}</p>
                                        </Cell>
                                        <Cell>{kpi.period}</Cell>
                                        <Cell className="font-mono text-base text-console-heading">{kpi.score}</Cell>
                                        <Cell>
                                            <Tag tone={ratingTone[kpi.rating]}>{kpi.rating}</Tag>
                                            {kpi.rewards > 0 && <p className="mt-1 text-xs text-console-dim">{kpi.rewards} reward{kpi.rewards === 1 ? '' : 's'}</p>}
                                        </Cell>
                                        <Cell className="max-w-xs text-console-muted">{kpi.remarks ?? '—'}</Cell>
                                        <Cell className="text-xs text-console-muted">
                                            {kpi.evaluator ?? '—'}
                                            {kpi.evaluatedAt && <span className="block font-mono text-console-dim">{timeAgo(kpi.evaluatedAt)}</span>}
                                        </Cell>
                                        <td className="py-3 text-right align-top">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => setRewarding({ employeeId: kpi.employee.id, kpiId: kpi.id })}
                                                    className="border border-arka-teal/40 px-3 py-1 text-xs font-medium text-arka-teal hover:bg-arka-teal/10"
                                                >
                                                    Reward
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setRemoving(kpi)}
                                                    className="px-3 py-1 text-xs text-console-muted hover:bg-console-raised hover:text-console-heading"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </td>
                                    </Row>
                                ))}
                            </Table>
                        ) : (
                            <Table columns={['Contractor', 'Reward', 'Amount', 'Linked evaluation', 'Description', 'Granted']} actions={false} isEmpty={rewards.length === 0} emptyMessage="No rewards granted yet." minWidth={980}>
                                {rewards.map((reward) => (
                                    <Row key={reward.id}>
                                        <Cell>
                                            <p className="font-medium text-console-heading">{reward.employee.name}</p>
                                            <p className="font-mono text-xs text-console-dim">{reward.employee.code}</p>
                                        </Cell>
                                        <Cell>
                                            <Tag tone="live">{reward.type}</Tag>
                                        </Cell>
                                        <Cell className="font-mono">{reward.amount !== null ? peso(reward.amount) : '—'}</Cell>
                                        <Cell className="text-console-muted">{reward.kpi ?? '—'}</Cell>
                                        <Cell className="max-w-xs text-console-muted">{reward.description ?? '—'}</Cell>
                                        <Cell className="text-xs text-console-muted">
                                            {reward.awarder ?? '—'}
                                            {reward.awardedAt && <span className="block font-mono text-console-dim">{timeAgo(reward.awardedAt)}</span>}
                                        </Cell>
                                    </Row>
                                ))}
                            </Table>
                        )}
                    </div>
                </Panel>
            </div>

            <EvaluateDialog open={evaluating} onClose={() => setEvaluating(false)} employees={employees} periods={periods} suggestion={suggestion} />
            <RewardDialog
                key={rewarding ? `${rewarding.employeeId}-${rewarding.kpiId}` : 'closed'}
                open={rewarding !== null}
                onClose={() => setRewarding(null)}
                employees={employees}
                rewardTypes={rewardTypes}
                kpis={kpis}
                preset={rewarding}
            />
            <ConfirmDialog
                open={removing !== null}
                title="Remove this evaluation?"
                body={removing ? `${removing.employee.name}'s ${removing.period} evaluation (${removing.score}) is removed. Rewards linked to it are kept.` : ''}
                confirmLabel="Remove evaluation"
                danger
                processing={processing}
                onConfirm={remove}
                onClose={() => setRemoving(null)}
            />
        </SuperAdminLayout>
    );
}
