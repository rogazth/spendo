import { cn } from '@/lib/utils';

interface CategoryAvatarProps {
    color: string;
    emoji: string | null;
    size?: 'sm' | 'md';
}

export function CategoryAvatar({ color, emoji, size = 'sm' }: CategoryAvatarProps) {
    const sizeClass =
        size === 'sm' ? 'size-5 text-[11px]' : 'size-6 text-[13px]';

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-md border leading-none',
                sizeClass,
            )}
            style={{
                backgroundColor: `${color}20`,
                borderColor: color,
            }}
            aria-hidden
        >
            {emoji ?? (
                <span
                    className="size-1.5 rounded-full"
                    style={{ backgroundColor: color }}
                />
            )}
        </span>
    );
}
