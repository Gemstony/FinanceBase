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
                            <div class="form-group mb-2">
                                <input type="text" id="calcDisplay" class="form-control form-control-lg text-right font-weight-bold" readonly placeholder="0">
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
                display.value = currentInput || '0';
                updateOperationDisplay();
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
                if (number === '.' && currentInput.includes('.')) return;
                if (currentInput === '0' && number !== '.') {
                    currentInput = number;
                } else {
                    currentInput += number;
                }
                updateDisplay();
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
                if (currentInput.length > 0) {
                    currentInput = currentInput.slice(0, -1);
                    updateDisplay();
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

            // Keyboard support
            $(document).on('keydown', function(e) {
                if ($('#calculatorModal').hasClass('show')) {
                    if (e.key >= '0' && e.key <= '9') appendNumber(e.key);
                    if (e.key === '.') appendNumber('.');
                    if (e.key === '+') chooseOperator('+');
                    if (e.key === '-') chooseOperator('-');
                    if (e.key === '*') chooseOperator('*');
                    if (e.key === '/') chooseOperator('/');
                    if (e.key === 'Enter' || e.key === '=') calculate();
                    if (e.key === 'Escape') clear();
                    if (e.key === 'Backspace') backspace();
                }
            });

            // Reset calculator when modal opens
            $('#calculatorModal').on('shown.bs.modal', function() {
                clear();
                $('#calcDisplay').focus();
            });
        });
    </script>
@stop
