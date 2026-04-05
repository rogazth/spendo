export type * from './auth';
export type * from './navigation';
export type * from './ui';
export type * from './models';
export {
    TRANSACTION_TYPES,
    INSTRUMENT_TYPES,
    BUDGET_FREQUENCIES,
} from './models';

import type { Auth } from './auth';
import type { Currency } from './models';

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    currencies?: Currency[];
    [key: string]: unknown;
};
