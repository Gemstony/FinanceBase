@extends('adminlte::master')

@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')
@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

@section('adminlte_css')
    @stack('css')
    @yield('css')
@stop

@section('classes_body', $layoutHelper->makeBodyClasses())

@section('body_data', $layoutHelper->makeBodyData())

@section('body')
    <div class="wrapper">

        {{-- Preloader Animation (fullscreen mode) --}}
        @if($preloaderHelper->isPreloaderEnabled())
            @include('adminlte::partials.common.preloader')
        @endif

        {{-- Top Navbar --}}
        @if($layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.navbar.navbar-layout-topnav')
        @else
            @include('adminlte::partials.navbar.navbar')
        @endif

        {{-- Left Main Sidebar --}}
        @if(!$layoutHelper->isLayoutTopnavEnabled())
            @include('adminlte::partials.sidebar.left-sidebar')
        @endif

        {{-- Content Wrapper --}}
        @empty($iFrameEnabled)
            @include('adminlte::partials.cwrapper.cwrapper-default')
        @else
            @include('adminlte::partials.cwrapper.cwrapper-iframe')
        @endempty

        {{-- Footer --}}
        @hasSection('footer')
            @include('adminlte::partials.footer.footer')
        @endif

        {{-- Right Control Sidebar --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.sidebar.right-sidebar')
        @endif

        {{-- Calculator Modal --}}
        <div class="modal fade" id="calculatorModal" tabindex="-1" role="dialog" aria-labelledby="calculatorModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white" id="calculatorModalLabel"><i class="fas fa-calculator"></i> Calculator</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="calculator">
                            <div class="form-group mb-1">
                                <div id="calcOperation" class="text-right text-muted small" style="height: 20px;"></div>
                            </div>
                            <div class="form-group mb-2 position-relative">
                                <input type="text" id="calcDisplay" class="form-control form-control-lg text-right font-weight-bold" placeholder="0" autocomplete="off" inputmode="decimal">
                                <small class="form-text text-muted text-right">Type, paste, or use buttons</small>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="clear">C</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="backspace"><i class="fas fa-backspace"></i></button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="operator" data-value="/">&divide;</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="operator" data-value="*">&times;</button></div>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="7">7</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="8">8</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="9">9</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="operator" data-value="-">-</button></div>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="4">4</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="5">5</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="6">6</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-secondary btn-block calc-btn" data-action="operator" data-value="+">+</button></div>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="1">1</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="2">2</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="3">3</button></div>
                                <div class="col-3 p-1"><button class="btn btn-primary btn-block calc-btn" data-action="calculate">=</button></div>
                            </div>
                            <div class="row no-gutters">
                                <div class="col-6 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value="0">0</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-primary btn-block calc-btn" data-action="number" data-value=".">.</button></div>
                                <div class="col-3 p-1"><button class="btn btn-outline-success btn-block calc-btn" data-action="copy" title="Copy result"><i class="fas fa-copy"></i></button></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@stop

@section('adminlte_js')
    @stack('js')
    @yield('js')

    {{-- Calculator JavaScript --}}
    <script>
        $(document).ready(function() {
            let currentInput = '';
            let previousInput = '';
            let operator = null;
            let shouldResetScreen = false;

            const display = document.getElementById('calcDisplay');

            function updateDisplay() {
                // Only update if not currently editing to avoid cursor jumping
                if (document.activeElement !== display) {
                    display.value = currentInput || '0';
                }
                updateOperationDisplay();
            }

            function syncFromInput() {
                // Sync currentInput from the display input value
                const val = display.value.trim();
                if (val === '' || val === '0') {
                    currentInput = '';
                } else {
                    // Remove any invalid characters, keep only digits, decimal point, and minus at start
                    let cleaned = val.replace(/[^0-9.-]/g, '');
                    // Ensure only one decimal point
                    const parts = cleaned.split('.');
                    if (parts.length > 2) {
                        cleaned = parts[0] + '.' + parts.slice(1).join('');
                    }
                    // Ensure minus only at start
                    if (cleaned.includes('-')) {
                        cleaned = cleaned.replace(/-/g, '');
                        if (val.startsWith('-')) {
                            cleaned = '-' + cleaned;
                        }
                    }
                    currentInput = cleaned;
                }
                updateDisplay();
            }

            function updateOperationDisplay() {
                const operationDisplay = document.getElementById('calcOperation');
                if (operator && previousInput) {
                    const operatorSymbol = operator === '*' ? '×' : operator === '/' ? '÷' : operator;
                    operationDisplay.textContent = previousInput + ' ' + operatorSymbol;
                } else {
                    operationDisplay.textContent = '';
                }
            }

            function clear() {
                currentInput = '';
                previousInput = '';
                operator = null;
                shouldResetScreen = false;
                updateDisplay();
                updateOperationDisplay();
            }

            function appendNumber(number) {
                if (shouldResetScreen) {
                    currentInput = '';
                    shouldResetScreen = false;
                }
                // Handle insertion at cursor position
                const cursorPos = display.selectionStart || currentInput.length;
                const before = currentInput.slice(0, cursorPos);
                const after = currentInput.slice(cursorPos);

                // Prevent multiple decimal points in the final result
                if (number === '.' && currentInput.includes('.')) return;

                if (currentInput === '0' && number !== '.') {
                    currentInput = number;
                } else {
                    currentInput = before + number + after;
                }
                updateDisplay();
                // Restore cursor position
                setTimeout(() => {
                    const newPos = cursorPos + 1;
                    display.setSelectionRange(newPos, newPos);
                    display.focus();
                }, 0);
            }

            function chooseOperator(op) {
                if (currentInput === '') return;
                if (previousInput !== '' && !shouldResetScreen) {
                    calculate();
                }
                operator = op;
                previousInput = currentInput;
                shouldResetScreen = true;
                updateOperationDisplay();
            }

            function calculate() {
                if (operator === null || previousInput === '' || currentInput === '') return;
                let computation;
                const prev = parseFloat(previousInput);
                const current = parseFloat(currentInput);
                if (isNaN(prev) || isNaN(current)) return;

                switch (operator) {
                    case '+':
                        computation = prev + current;
                        break;
                    case '-':
                        computation = prev - current;
                        break;
                    case '*':
                        computation = prev * current;
                        break;
                    case '/':
                        if (current === 0) {
                            alert('Cannot divide by zero');
                            return;
                        }
                        computation = prev / current;
                        break;
                    default:
                        return;
                }

                currentInput = computation.toString();
                operator = null;
                previousInput = '';
                shouldResetScreen = true;
                updateDisplay();
                updateOperationDisplay();
            }

            function backspace() {
                const cursorPos = display.selectionStart || currentInput.length;
                const selectionEnd = display.selectionEnd || cursorPos;

                if (cursorPos !== selectionEnd) {
                    // Delete selected text
                    const before = currentInput.slice(0, cursorPos);
                    const after = currentInput.slice(selectionEnd);
                    currentInput = before + after;
                } else if (cursorPos > 0) {
                    // Delete character before cursor
                    const before = currentInput.slice(0, cursorPos - 1);
                    const after = currentInput.slice(cursorPos);
                    currentInput = before + after;
                }
                updateDisplay();
                // Restore cursor position
                setTimeout(() => {
                    const newPos = Math.max(0, cursorPos - 1);
                    display.setSelectionRange(newPos, newPos);
                    display.focus();
                }, 0);
            }

            function handlePaste(e) {
                e.preventDefault();
                const evt = e.originalEvent || e;
                const clipboardData = evt.clipboardData || window.clipboardData;
                if (!clipboardData || typeof clipboardData.getData !== 'function') {
                    return;
                }

                const pastedText = clipboardData.getData('text');

                // Clean the pasted text - keep only valid number characters
                let cleaned = pastedText.replace(/[^0-9.-]/g, '');

                // Ensure only one decimal point
                const parts = cleaned.split('.');
                if (parts.length > 2) {
                    cleaned = parts[0] + '.' + parts.slice(1).join('');
                }

                // Handle minus sign
                if (cleaned.includes('-')) {
                    const isNegative = cleaned.startsWith('-') || pastedText.trim().startsWith('-');
                    cleaned = cleaned.replace(/-/g, '');
                    if (isNegative) {
                        cleaned = '-' + cleaned;
                    }
                }

                if (cleaned) {
                    if (shouldResetScreen) {
                        currentInput = cleaned;
                        shouldResetScreen = false;
                    } else {
                        // Insert at cursor position
                        const cursorPos = display.selectionStart || currentInput.length;
                        const before = currentInput.slice(0, cursorPos);
                        const after = currentInput.slice(display.selectionEnd || cursorPos);
                        currentInput = before + cleaned + after;
                    }
                    updateDisplay();
                    // Set cursor after pasted content
                    setTimeout(() => {
                        const start = display.selectionStart || 0;
                        const newPos = start + cleaned.length;
                        display.setSelectionRange(newPos, newPos);
                        display.focus();
                    }, 0);
                }
            }

            function copyToClipboard() {
                if (currentInput) {
                    navigator.clipboard.writeText(currentInput).then(function() {
                        // Show brief feedback
                        const btn = $('[data-action="copy"]');
                        const originalHtml = btn.html();
                        btn.html('<i class="fas fa-check"></i>');
                        setTimeout(function() {
                            btn.html(originalHtml);
                        }, 1000);
                    });
                }
            }

            // Calculator button click handlers
            $(document).on('click', '.calc-btn', function() {
                const action = $(this).data('action');
                const value = $(this).data('value');

                switch(action) {
                    case 'number':
                        appendNumber(value);
                        break;
                    case 'operator':
                        chooseOperator(value);
                        break;
                    case 'calculate':
                        calculate();
                        break;
                    case 'clear':
                        clear();
                        break;
                    case 'backspace':
                        backspace();
                        break;
                    case 'copy':
                        copyToClipboard();
                        break;
                }
            });

            // Handle input changes (typing, drag-drop, etc.)
            $(display).on('input', function(e) {
                // Skip if this was triggered by our own updates
                if (e.originalEvent && e.originalEvent.inputType === 'insertFromPaste') return;
                syncFromInput();
            });

            // Handle paste event
            $(display).on('paste', handlePaste);

            // Handle keydown for special keys (when not typing text)
            $(display).on('keydown', function(e) {
                // Allow these keys to work normally for editing
                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Tab'].includes(e.key)) {
                    return;
                }

                // Handle calculator operations
                if (e.key === 'Enter') {
                    e.preventDefault();
                    syncFromInput();
                    calculate();
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    clear();
                    return;
                }
                if (e.key === '+') {
                    e.preventDefault();
                    syncFromInput();
                    chooseOperator('+');
                    return;
                }
                if (e.key === '-') {
                    // Allow minus for negative numbers if at start
                    const cursorPos = display.selectionStart || 0;
                    if (cursorPos === 0 && !currentInput.startsWith('-')) {
                        return; // Allow typing minus at start
                    }
                    e.preventDefault();
                    syncFromInput();
                    chooseOperator('-');
                    return;
                }
                if (e.key === '*') {
                    e.preventDefault();
                    syncFromInput();
                    chooseOperator('*');
                    return;
                }
                if (e.key === '/') {
                    e.preventDefault();
                    syncFromInput();
                    chooseOperator('/');
                    return;
                }
                if (e.key === '=') {
                    e.preventDefault();
                    syncFromInput();
                    calculate();
                    return;
                }
                if (e.key === 'Backspace') {
                    // Let default backspace work for editing, unless we want to handle selection
                    if (display.selectionStart !== display.selectionEnd) {
                        e.preventDefault();
                        backspace();
                    }
                    return;
                }

                // Allow only valid number characters
                if (e.key.length === 1 && !/[0-9.]/.test(e.key)) {
                    e.preventDefault();
                    return;
                }

                // Prevent multiple decimal points
                if (e.key === '.' && currentInput.includes('.') && !isDecimalPartSelected()) {
                    e.preventDefault();
                    return;
                }
            });

            function isDecimalPartSelected() {
                const cursorPos = display.selectionStart || 0;
                const selectionEnd = display.selectionEnd || cursorPos;
                const decimalIndex = currentInput.indexOf('.');
                if (decimalIndex === -1) return false;
                // Check if the selection includes the decimal point
                return cursorPos <= decimalIndex && selectionEnd > decimalIndex;
            }

            // Global keyboard shortcuts when modal is shown (for button clicks)
            $(document).on('keydown', function(e) {
                if ($('#calculatorModal').hasClass('show')) {
                    // These are handled by the input's keydown, but handle here for button effects
                    if (e.key >= '0' && e.key <= '9') {
                        highlightButton('number', e.key);
                    }
                }
            });

            function highlightButton(action, value) {
                const btn = $(`.calc-btn[data-action="${action}"][data-value="${value}"]`);
                if (btn.length) {
                    btn.addClass('active');
                    setTimeout(() => btn.removeClass('active'), 100);
                }
            }

            // Reset calculator when modal opens
            $('#calculatorModal').on('shown.bs.modal', function() {
                clear();
                $('#calcDisplay').focus();
            });
        });
    </script>
@stop
