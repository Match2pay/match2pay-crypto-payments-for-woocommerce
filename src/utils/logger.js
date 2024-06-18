const debug = true

export const logger = {
    log: ( ...args ) => {
        if ( debug ) {
            // eslint-disable-next-line no-console
            console.log( ...args );
        }
    },
    error: ( ...args ) => {
        if ( debug ) {
            // eslint-disable-next-line no-console
            console.error( ...args );
        }
    },
    warn: ( ...args ) => {
        if ( debug ) {
            // eslint-disable-next-line no-console
            console.warn( ...args );
        }
    }
};
