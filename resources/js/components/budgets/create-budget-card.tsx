import { PlusIcon } from 'lucide-react';

interface CreateBudgetCardProps {
    onClick: () => void;
}

export function CreateBudgetCard({ onClick }: CreateBudgetCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="group border-border hover:border-foreground/30 hover:bg-muted/40 focus-visible:border-foreground/30 focus-visible:outline-hidden flex min-h-[200px] flex-col items-center justify-center rounded-xl border-2 border-dashed bg-transparent p-6 text-center transition-all"
        >
            <div className="bg-muted mb-3 flex size-12 items-center justify-center rounded-full transition-transform group-hover:scale-105">
                <PlusIcon className="text-muted-foreground size-5" />
            </div>
            <h3 className="text-foreground mb-1 font-medium">
                Create new budget
            </h3>
            <p className="text-muted-foreground text-xs">
                Set limits for a new category
            </p>
        </button>
    );
}
