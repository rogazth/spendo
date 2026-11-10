import { PlusIcon } from 'lucide-react';

interface CreateBudgetCardProps {
    onClick: () => void;
}

export function CreateBudgetCard({ onClick }: CreateBudgetCardProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="group flex min-h-[200px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-border bg-transparent p-6 text-center transition-all hover:border-foreground/30 hover:bg-muted/40 focus-visible:border-foreground/30 focus-visible:outline-hidden"
        >
            <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-muted transition-transform group-hover:scale-105">
                <PlusIcon className="size-5 text-muted-foreground" />
            </div>
            <h3 className="mb-1 font-medium text-foreground">
                Create new budget
            </h3>
            <p className="text-xs text-muted-foreground">
                Set limits for a new category
            </p>
        </button>
    );
}
