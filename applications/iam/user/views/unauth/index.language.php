<?php

// START - ENG ========================================================

namespace language\en\applications\iam\user\views\unauth\buttons {
    const back = 'Return to main page';
}

namespace language\en\applications\iam\user\views\unauth\problem {
    define(__namespace__ . '\\' . '500', 'Internal Server Error 500');
    define(__namespace__ . '\\' . '502', 'Error 502 Bad Gateway');
    define(__namespace__ . '\\' . '504', 'Error 504 Timeout');
    define(__namespace__ . '\\' . '403', 'Error 403 Forbidden');
    define(__namespace__ . '\\' . '404', 'Error 404 Page not found');
    const policy = 'Wait for the policies from your administrator!';
}

// END - ENG ==========================================================

// START - ITA ========================================================

namespace language\it\applications\iam\user\views\unauth\buttons {
    const back = 'Torna alla pagina principale';
}

namespace language\it\applications\iam\user\views\unauth\problem {
    const policy = 'Attendi le policy dal tuo amministratore!';
}
// END - ITA ==========================================================
