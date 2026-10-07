(() => {
    const hasSpamPattern = (value) => {
        const normalized = value.toLocaleLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
        if (/(\p{L})\1{3,}/u.test(normalized)) {
            return true;
        }
        const rows = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
        if (rows.some((row) => [row, [...row].reverse().join('')].some((sequence) => {
            for (let index = 0; index <= sequence.length - 3; index += 1) {
                if (normalized.includes(sequence.slice(index, index + 3))) {
                    return true;
                }
            }
            return false;
        }))) {
            return true;
        }

        const words = value.toLocaleLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean);
        return words.some((word) => {
            const characters = [...word];
            for (let size = 2; size <= Math.min(8, Math.floor(characters.length / 2)); size += 1) {
                const pattern = characters.slice(0, size).join('');
                let repetitions = 0;
                while (characters.slice(repetitions * size, (repetitions + 1) * size).join('') === pattern) {
                    repetitions += 1;
                }
                if (repetitions >= 3 || (
                    characters.length >= 5
                    && repetitions >= 2
                    && repetitions * size < characters.length
                )) {
                    return true;
                }
            }

            if (/^[a-z]{5,8}$/u.test(word) && (word.match(/[aeiouy]/gu) || []).length <= 1) {
                const letterCounts = new Map();
                characters.forEach((character) => {
                    letterCounts.set(character, (letterCounts.get(character) || 0) + 1);
                });
                const repeatedLetters = [...letterCounts.values()]
                    .reduce((total, count) => total + Math.max(0, count - 1), 0);
                return repeatedLetters >= 3;
            }
            return false;
        });
    };

    const toggle = document.querySelector('.menu-toggle');
    const nav = document.querySelector('.main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    const role = document.querySelector('#account-role');
    const companyFields = document.querySelector('#company-fields');
    if (role && companyFields) {
        const companyName = companyFields.querySelector('[name="company_name"]');
        const updateCompanyFields = () => {
            const employer = role.value === 'employer';
            companyFields.hidden = !employer;
            companyName.required = employer;
        };
        role.addEventListener('change', updateCompanyFields);
        updateCompanyFields();
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form textarea').forEach((textarea) => {
        const validateTextarea = () => {
            if (textarea.closest('[hidden]')) {
                textarea.setCustomValidity('');
                return;
            }
            const value = textarea.value.trim();
            const meaningfulLength = (value.match(/[\p{L}\p{N}]/gu) || []).length;
            const letters = (value.match(/\p{L}/gu) || []).length;
            const numbers = (value.match(/\p{N}/gu) || []).length;
            const userMessage = textarea.hasAttribute('data-user-message');
            const shortReply = value.toLocaleLowerCase().replace(/[^\p{L}]/gu, '');
            if (value === '' && textarea.required) {
                textarea.setCustomValidity('This field is required. Enter meaningful information.');
            } else if (value !== '' && hasSpamPattern(value)) {
                textarea.setCustomValidity('Repeated characters, keyboard patterns, or random-looking text are not allowed.');
            } else if (userMessage && value !== '' && (
                (!['hi', 'ok', 'yes', 'no', 'thanks'].includes(shortReply) && letters < 3)
                || meaningfulLength / value.length < 0.5
                || (value.length - meaningfulLength) / value.length > 0.4
                || numbers / value.length > 0.4
            )) {
                textarea.setCustomValidity('Write a clear message, or use a short reply such as OK or Thanks.');
            } else if (value !== '' && !userMessage && meaningfulLength < 5) {
                textarea.setCustomValidity('Enter at least 5 letters or numbers so the information is meaningful.');
            } else {
                textarea.setCustomValidity('');
            }
        };
        textarea.addEventListener('input', validateTextarea);
        textarea.addEventListener('blur', validateTextarea);
        textarea.form.addEventListener('submit', (event) => {
            validateTextarea();
            if (!textarea.checkValidity()) {
                event.preventDefault();
                textarea.reportValidity();
            }
        });
    });

    document.querySelectorAll('form input:not([type]), form input[type="text"], form input[type="tel"]')
        .forEach((input) => {
            const validateInput = () => {
                if (input.closest('[hidden]')) {
                    input.setCustomValidity('');
                    return;
                }
                const value = input.value.trim();
                const meaningfulLength = (value.match(/[\p{L}\p{N}]/gu) || []).length;
                const phone = value.replace(/[\s().-]/g, '');
                const telebirrAccount = input.name === 'payout_account_number'
                    && input.form.querySelector('[name="payout_provider"]')
                    && input.form.querySelector('[name="payout_provider"]').value === 'Telebirr';
                if ((input.type === 'tel' || telebirrAccount) && value !== '' && !(/^(?:0[79]\d{8}|\+?251[79]\d{8})$/.test(phone))) {
                    input.setCustomValidity('Enter a valid Ethiopian mobile number, such as 0912345678 or +251912345678.');
                } else if (value === '' && input.required) {
                    input.setCustomValidity('This field is required. Enter meaningful information.');
                } else if (value !== '' && meaningfulLength < 2) {
                    input.setCustomValidity('Enter meaningful information using at least 2 letters or numbers.');
                } else if (value !== '' && hasSpamPattern(value)) {
                    input.setCustomValidity('Repeated characters, keyboard patterns, or random text are not allowed.');
                } else {
                    input.setCustomValidity('');
                }
            };
            input.addEventListener('input', validateInput);
            input.addEventListener('blur', validateInput);
            input.form.addEventListener('submit', (event) => {
                validateInput();
                if (!input.checkValidity()) {
                    event.preventDefault();
                    input.reportValidity();
                }
            });
        });
})();
