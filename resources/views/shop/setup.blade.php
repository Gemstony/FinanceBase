<!DOCTYPE html>
<html lang="sw">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tengeneza Microfinance Yako</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(180deg, #004e92, #000428);
            min-height: 100vh;
        }

        .dukabase-header h1 {
            color: #004e92;
            font-size: 3rem;

        }
    </style>
</head>

<body class="antialiased">
    <div class="container mx-auto px-4 py-8 max-w-4xl">

        <div class="bg-white shadow-2xl rounded-2xl p-8">
            <!-- Header -->
            <div class="text-center m-8 dukabase-header">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">FinanceBase</h1>
            </div>
            <hr>
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Tengeneza Branch Yako</h1>
                <p class="text-gray-600">Kabla ya kuendelea, tengeneza Branch lako kubwa na angalau (Branch) moja</p>
            </div>

            <!-- Error Messages -->
            @if($errors->any())
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <strong>Kuna makosa!</strong>
                    </div>
                    <ul class="list-disc list-inside ml-7">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Success Message -->
            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            <form action="{{ route('shop.store') }}" method="POST" id="shopForm">
                @csrf

                <!-- Main Branch Section -->
                <div class="mb-8 border-b-2 border-gray-200 pb-6">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center text-gray-800">
                        <svg class="w-7 h-7 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        Branch Kubwa (Main Brach)
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Jina la Branch <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="shop_name" value="{{ old('shop_name') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                placeholder="Mfano: Branch ya Manzese" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Namba ya Simu <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" name="shop_phone" value="{{ old('shop_phone') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                placeholder="Mfano: 0712345678" required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Anuani <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="shop_address" value="{{ old('shop_address') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                placeholder="Mfano: Mtaa wa Uhuru, Dar es Salaam" required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Maelezo (Optional)
                            </label>
                            <textarea name="shop_description" rows="3"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                placeholder="Eleza kuhusu Branch yako...">{{ old('shop_description') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Sub Shops Section -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold flex items-center text-gray-800">
                            <svg class="w-7 h-7 mr-3 text-purple-600" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                </path>
                            </svg>
                            Branch Ndogo ndogo (Branch)
                        </h2>
                        <button type="button" id="addSubShopBtn" onclick="addSubShop()"
                            class="bg-green-500 hover:bg-green-600 text-white font-semibold px-5 py-2.5 rounded-lg text-sm flex items-center shadow-md hover:shadow-lg transition transform hover:scale-105">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            Ongeza (Branch)
                        </button>
                    </div>

                    <div id="subShopsContainer">
                        <!-- First Branch (Required) -->
                        <div
                            class="subshop-item bg-gradient-to-br from-purple-50 to-blue-50 p-6 rounded-xl mb-5 border-2 border-purple-200 shadow-md">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-semibold text-lg text-gray-800 flex items-center">
                                    <span
                                        class="bg-purple-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">1</span>
                                    (Branch) 1
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Jina la (Branch) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subshops[0][name]" value="{{ old('subshops.0.name') }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition"
                                        placeholder="Mfano: Tawi la Kariakoo" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Namba ya Simu <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="subshops[0][phone]" value="{{ old('subshops.0.phone') }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition"
                                        placeholder="Mfano: 0712345678" required>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        Anuani <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="subshops[0][address]"
                                        value="{{ old('subshops.0.address') }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition"
                                        placeholder="Mfano: Kariakoo, Dar es Salaam" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t-2 border-gray-200">
                    <button type="submit"
                        class="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white font-bold px-10 py-4 rounded-xl transition duration-300 flex items-center shadow-lg hover:shadow-xl transform hover:scale-105">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                        Tengeneza Branch
                    </button>
                </div>
            </form>






            
        </div>


        
        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-white text-sm">&copy; 2025 FinanceBase. All rights reserved.</p>
        </div>
    </div>

    <script>
        let subShopCount = 1;

        function getCurrentSubshopCount() {
            return document.querySelectorAll('#subShopsContainer .subshop-item').length;
        }

        function updateAddButtonState() {
            const btn = document.getElementById('addSubShopBtn');
            const count = getCurrentSubshopCount();
            const atLimit = count >= 2;
            if (btn) {
                btn.disabled = atLimit;
                btn.classList.toggle('opacity-60', atLimit);
                btn.classList.toggle('cursor-not-allowed', atLimit);
                btn.classList.toggle('hover:scale-105', !atLimit);
            }
        }

        function addSubShop() {
            const container = document.getElementById('subShopsContainer');
            const current = getCurrentSubshopCount();
            if (current >= 2) {
                // Optional lightweight notice
                if (!document.getElementById('subshopLimitNotice')) {
                    const notice = document.createElement('div');
                    notice.id = 'subshopLimitNotice';
                    notice.className = 'mb-4 text-sm text-red-600';
                    notice.textContent = 'Unaweza kuongeza zaidi ya (Branch) 2 tu.';
                    container.parentElement.insertBefore(notice, container);
                }
                updateAddButtonState();
                return;
            }

            const newIndex = subShopCount;

            const newSubShop = `
                <div class="subshop-item bg-gradient-to-br from-purple-50 to-blue-50 p-6 rounded-xl mb-5 border-2 border-purple-200 shadow-md animate-fade-in">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-lg text-gray-800 flex items-center">
                            <span class="bg-purple-600 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3 text-sm">${newIndex + 1}</span>
                            (Branch) ${newIndex + 1}
                        </h3>
                        <button type="button" onclick="removeSubShop(this)" 
                                class="bg-red-500 hover:bg-red-600 text-white font-semibold px-4 py-2 rounded-lg text-sm shadow-md hover:shadow-lg transition transform hover:scale-105 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Ondoa
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Jina la (Branch) <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subshops[${newIndex}][name]" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition" 
                                   placeholder="Mfano: Tawi la Kariakoo" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Namba ya Simu <span class="text-red-500">*</span>
                            </label>
                            <input type="tel" name="subshops[${newIndex}][phone]" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition" 
                                   placeholder="Mfano: 0712345678" required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Anuani <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subshops[${newIndex}][address]" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white transition" 
                                   placeholder="Mfano: Kariakoo, Dar es Salaam" required>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', newSubShop);
            subShopCount++;
            updateAddButtonState();

            // Scroll to new subshop
            setTimeout(() => {
                const newElement = container.lastElementChild;
                newElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }

        function removeSubShop(button) {
            const item = button.closest('.subshop-item');
            item.style.opacity = '0';
            item.style.transform = 'scale(0.95)';
            item.style.transition = 'all 0.3s ease';

            setTimeout(() => {
                item.remove();
                updateSubShopNumbers();
                updateAddButtonState();
            }, 300);
        }

        function updateSubShopNumbers() {
            const subshops = document.querySelectorAll('.subshop-item');
            subshops.forEach((item, index) => {
                const numberBadge = item.querySelector('span.bg-purple-600');
                const heading = item.querySelector('h3');
                if (numberBadge) numberBadge.textContent = index + 1;
                if (heading) {
                    const textNode = heading.childNodes[heading.childNodes.length - 1];
                    textNode.textContent = ` (Branch) ${index + 1}`;
                }
            });
        }

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fade-in {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in {
                animation: fade-in 0.3s ease;
            }
        `;
        document.head.appendChild(style);
        // Initialize button state on load
        document.addEventListener('DOMContentLoaded', updateAddButtonState);
    </script>
</body>

</html>