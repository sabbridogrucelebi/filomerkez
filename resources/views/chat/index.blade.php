@extends('layouts.app')

@section('title', 'PilotChat')
@section('subtitle', 'Kurum İçi İletişim')

@section('content')

<style>
    .ios-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
    .ios-scroll::-webkit-scrollbar-track { background: transparent; }
    .ios-scroll::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,.15); border-radius: 10px; }
    .ios-scroll:hover::-webkit-scrollbar-thumb { background-color: rgba(0,0,0,.3); }

    .chat-bg {
        background-color: #EFEFEF;
        background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2325D366' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    .bubble-received { background-color: #FFFFFF; border-radius: 20px 20px 20px 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .bubble-sent { background-color: #DCF8C6; border-radius: 20px 20px 4px 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
</style>

<div class="max-w-[1200px] mx-auto h-[calc(100vh-140px)] flex gap-4 p-4 lg:p-6" x-data="chatApp()" x-init="init()">
    
    {{-- SOL PANEL (Chats List) --}}
    <div class="w-full lg:w-[400px] flex-shrink-0 bg-white rounded-[32px] shadow-sm border border-slate-100 flex flex-col overflow-hidden relative z-10" :class="{ 'hidden lg:flex': activeChat }">
        
        <div class="pt-6 px-6 pb-2 flex justify-between items-center bg-white shrink-0">
            <template x-if="!selectMode">
                <div class="flex justify-between items-center w-full">
                    <button @click="selectMode = true" class="text-[#25D366] hover:bg-[#E7FFDB] px-3 py-1 rounded-full transition-colors text-sm font-semibold">Düzenle</button>
                    <div class="flex items-center gap-2">
                        <button @click="openGroupModal = true" class="bg-[#25D366] text-white p-2 rounded-full hover:bg-[#1EBE5D] transition-colors shadow-md shadow-[#25D366]/30">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        </button>
                    </div>
                </div>
            </template>
            <template x-if="selectMode">
                <div class="flex justify-between items-center w-full">
                    <button @click="selectMode = false; selectedConvIds = []" class="text-slate-500 hover:text-black text-sm font-semibold">İptal</button>
                    <span class="text-sm font-bold" x-text="selectedConvIds.length + ' seçili'"></span>
                    <button @click="bulkDeleteConvs()" :disabled="selectedConvIds.length === 0" class="text-red-500 hover:text-red-700 disabled:opacity-30 text-sm font-bold">Toplu Sil</button>
                </div>
            </template>
        </div>

        <div class="px-6 pb-4 bg-white shrink-0 border-b border-slate-50">
            <h1 class="text-[34px] font-bold text-black tracking-tight leading-none">Sohbetler</h1>
        </div>

        <div class="px-6 py-2 shrink-0 bg-white">
            <div class="bg-[#F0F2F5] rounded-xl flex items-center h-10 px-3">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#8696A0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" x-model="searchQuery" placeholder="Ara" class="bg-transparent border-none focus:ring-0 w-full text-[15px] text-black px-3 placeholder-[#8696A0]">
            </div>
        </div>

        <div class="flex-1 overflow-y-auto ios-scroll bg-white pb-[70px]">
            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="selectMode ? toggleConvSelection(conv.id) : selectChat(conv)" class="flex items-center px-4 py-3 cursor-pointer hover:bg-[#F5F6F6] transition-colors group relative">
                    <!-- Select checkbox -->
                    <div x-show="selectMode" class="mr-3 shrink-0">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors" :class="selectedConvIds.includes(conv.id) ? 'bg-red-500 border-red-500' : 'border-slate-300'">
                            <svg x-show="selectedConvIds.includes(conv.id)" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                    </div>
                    <div class="relative shrink-0 mr-4">
                        <div class="w-14 h-14 rounded-full bg-slate-200 flex items-center justify-center font-bold text-xl text-slate-500 overflow-hidden border border-slate-100 shadow-sm">
                            <img x-show="conv.profile_photo_url" :src="conv.profile_photo_url" class="w-full h-full object-cover" />
                            <span x-show="!conv.profile_photo_url && conv.type === 'direct'" x-text="conv.name?.substring(0, 1) || '?'"></span>
                            <svg x-show="!conv.profile_photo_url && conv.type === 'group'" viewBox="0 0 24 24" width="28" height="28" fill="currentColor" class="opacity-50"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 border-b border-slate-100 group-hover:border-transparent pb-3 pt-1">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[17px] font-semibold text-black truncate" x-text="conv.name"></span>
                            <span class="text-[13px] font-medium" :class="conv.unread_count > 0 ? 'text-[#25D366]' : 'text-slate-400'" x-text="conv.last_message_time || ''"></span>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <div class="flex items-center gap-1 text-[15px] text-[#667781] truncate flex-1">
                                <span class="truncate" x-text="conv.last_message || 'Henüz mesaj yok...'"></span>
                            </div>
                            <div x-show="conv.unread_count > 0" class="shrink-0 bg-[#25D366] text-white text-[12px] font-bold min-w-[22px] h-[22px] px-1.5 rounded-full flex items-center justify-center shadow-sm shadow-[#25D366]/30" x-text="conv.unread_count"></div>
                        </div>
                    </div>
                    <!-- Delete conversation button -->
                    <button @click.stop="deleteConversation(conv)" class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity text-slate-400 hover:text-red-500 p-1 rounded-full hover:bg-red-50" title="Sohbeti Sil">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Bottom Tab Bar --}}
        <div class="absolute bottom-0 left-0 right-0 h-[65px] bg-white/95 backdrop-blur-md border-t border-slate-100 flex justify-between items-center px-6 pb-2 pt-1 shrink-0 z-20">
            <button class="flex flex-col items-center gap-1 text-slate-400 hover:text-black transition-colors"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg><span class="text-[10px] font-semibold">Güncellemeler</span></button>
            <button class="flex flex-col items-center gap-1 text-slate-400 hover:text-black transition-colors"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg><span class="text-[10px] font-semibold">Aramalar</span></button>
            <button class="flex flex-col items-center gap-1 text-slate-400 hover:text-black transition-colors"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span class="text-[10px] font-semibold">Topluluklar</span></button>
            <button class="flex flex-col items-center gap-1 text-black relative"><div class="relative"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div><span class="text-[10px] font-semibold">Sohbetler</span></button>
            <button class="flex flex-col items-center gap-1 text-slate-400 hover:text-black transition-colors"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg><span class="text-[10px] font-semibold">Ayarlar</span></button>
        </div>
    </div>

    {{-- SAĞ PANEL (Chat Detail) --}}
    <div class="flex-1 bg-white lg:rounded-[32px] rounded-[16px] shadow-sm border border-slate-100 flex flex-col overflow-hidden relative z-10" :class="{ 'hidden lg:flex': !activeChat }">
        
        <div x-show="!activeChat" class="absolute inset-0 flex flex-col items-center justify-center text-center p-8 bg-slate-50 z-10">
            <div class="w-32 h-32 mb-6 text-[#25D366]/40">
                <svg viewBox="0 0 24 24" fill="currentColor" class="w-full h-full"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h1 class="text-[28px] font-bold text-black mb-2 tracking-tight">PilotChat Web</h1>
            <p class="text-[15px] text-slate-500 max-w-sm leading-relaxed">Mesajlaşmaya başlamak için bir sohbet seçin veya yeni grup kurun. Kurumunuz için uçtan uca şifrelidir.</p>
        </div>

        <div x-show="activeChat" class="h-[75px] px-4 lg:px-6 bg-white/95 backdrop-blur-md flex items-center justify-between shrink-0 z-20 border-b border-slate-100 shadow-sm shadow-black/5 absolute top-0 left-0 right-0">
            <div class="flex items-center gap-3 cursor-pointer">
                <button @click="activeChat = null" class="lg:hidden text-[#25D366] -ml-2 p-2">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div class="w-11 h-11 rounded-full bg-slate-200 overflow-hidden flex items-center justify-center text-slate-500 font-bold text-lg border border-slate-100">
                    <span x-show="activeChat?.type === 'direct'" x-text="activeChat?.name?.substring(0, 1) || '?'"></span>
                    <svg x-show="activeChat?.type === 'group'" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" class="opacity-50"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
                </div>
                <div class="flex flex-col justify-center">
                    <h2 class="text-[17px] font-semibold text-black leading-tight" x-text="activeChat?.name"></h2>
                    <p class="text-[12px] text-slate-400" x-show="activeChat?.type === 'group'" x-text="activeChat?.participants"></p>
                    <p class="text-[12px] text-slate-400" x-show="activeChat?.type === 'direct'">iletişim bilgisi için tıklayın</p>
                </div>
            </div>
            <div class="flex items-center gap-4 text-[#25D366]">
                <button title="Video Call" class="p-2 hover:bg-[#E7FFDB] rounded-full transition-colors"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg></button>
                <button title="Voice Call" class="p-2 hover:bg-[#E7FFDB] rounded-full transition-colors"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg></button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto ios-scroll chat-bg px-4 lg:px-8 py-6 flex flex-col gap-[6px] mt-[75px]" id="messages-container" x-ref="messagesContainer">
            <template x-for="(msg, index) in messages" :key="msg.id">
                <div class="flex w-full group/msg" :class="msg.is_mine ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[75%] lg:max-w-[60%] relative px-4 py-2 flex flex-col" :class="[msg.is_mine ? 'bubble-sent' : 'bubble-received', msg.is_deleted ? 'opacity-50' : '']" style="min-width: 80px;">
                        
                        <div x-show="activeChat?.type === 'group' && !msg.is_mine" class="text-[13px] font-bold text-[#c0316e] mb-0.5" x-text="msg.sender_name"></div>
                        
                        <!-- Attachments -->
                        <template x-if="msg.attachments && msg.attachments.length > 0">
                            <div class="flex flex-col gap-2 mb-2">
                                <template x-for="att in msg.attachments" :key="att.id">
                                    <a :href="att.url" target="_blank" class="block border rounded-lg p-2 bg-black/5 hover:bg-black/10 transition-colors">
                                        <template x-if="att.mime_type && att.mime_type.startsWith('image/')">
                                            <img :src="att.url" class="max-w-[300px] rounded-lg" />
                                        </template>
                                        <template x-if="!att.mime_type || !att.mime_type.startsWith('image/')">
                                            <div class="flex items-center gap-2">
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-500"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                                <span class="text-sm truncate font-medium text-slate-700" x-text="att.filename"></span>
                                            </div>
                                        </template>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <span class="text-[15.5px] text-black leading-snug whitespace-pre-wrap block pb-4" style="word-break: break-word;" x-text="msg.body" x-show="msg.body" :class="msg.is_deleted ? 'italic' : ''"></span>
                        
                        <div class="absolute right-3 bottom-1.5 flex items-center gap-1">
                            <span class="text-[10px] font-medium" :class="msg.is_mine ? 'text-[#5E8B48]' : 'text-slate-400'" x-text="msg.time"></span>
                            <span x-show="msg.is_mine && !msg.is_deleted">
                                <svg x-show="msg.is_read" viewBox="0 0 16 15" width="14" height="14" fill="none" stroke="#34B7F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 4 6 13 1 8"></polyline><polyline points="10 4 6.5 7.5"></polyline></svg>
                                <svg x-show="!msg.is_read" viewBox="0 0 16 15" width="14" height="14" fill="none" stroke="currentColor" class="text-[#5E8B48]/70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 4 6 13 1 8"></polyline></svg>
                            </span>
                        </div>

                        <!-- Delete dropdown on hover -->
                        <div x-show="!msg.is_deleted" class="absolute -top-2 opacity-0 group-hover/msg:opacity-100 transition-opacity z-10" :class="msg.is_mine ? 'left-0' : 'right-0'">
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="bg-white shadow-md rounded-full p-1.5 text-slate-400 hover:text-slate-600">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="12" cy="5" r="2"></circle><circle cx="12" cy="12" r="2"></circle><circle cx="12" cy="19" r="2"></circle></svg>
                                </button>
                                <div x-show="open" @click.away="open = false" class="absolute top-full mt-1 bg-white rounded-xl shadow-xl border border-slate-100 py-1 min-w-[160px] z-50" :class="msg.is_mine ? 'left-0' : 'right-0'">
                                    <button x-show="msg.is_mine" @click="deleteMsg(msg, true); open = false" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-red-50 font-medium">Herkesten Sil</button>
                                    <button @click="deleteMsg(msg, false); open = false" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 font-medium">Benden Sil</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input Area --}}
        <div x-show="activeChat" class="min-h-[70px] bg-[#F0F2F5] flex items-end px-3 py-3 gap-3 shrink-0 z-20 border-t border-slate-200">
            <input type="file" x-ref="fileInput" class="hidden" multiple @change="handleFileSelect">
            
            <button @click="$refs.fileInput.click()" class="text-[#25D366] p-2 hover:bg-black/5 rounded-full transition-colors mb-1 relative">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <div x-show="selectedFiles.length > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold" x-text="selectedFiles.length"></div>
            </button>
            
            <div class="flex-1 bg-white rounded-[24px] flex flex-col min-h-[44px] border border-slate-300 shadow-sm px-4">
                <div x-show="selectedFiles.length > 0" class="flex flex-wrap gap-2 pt-2 border-b border-slate-100 pb-2">
                    <template x-for="(file, i) in selectedFiles">
                        <div class="bg-slate-100 rounded text-xs px-2 py-1 flex items-center gap-1">
                            <span class="truncate max-w-[100px]" x-text="file.name"></span>
                            <button @click="removeFile(i)" class="text-red-500 hover:text-red-700 font-bold">&times;</button>
                        </div>
                    </template>
                </div>
                <div class="flex items-end">
                    <textarea x-model="newMessage" @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()" rows="1" class="w-full bg-transparent border-none focus:ring-0 resize-none py-2.5 text-[16px] max-h-28 ios-scroll" placeholder="Bir mesaj yazın"></textarea>
                    <button class="text-[#8696A0] pb-2.5 hover:text-[#25D366] transition-colors pl-2">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                    </button>
                </div>
            </div>
            
            <button @click="sendMessage" :disabled="isSending || (!newMessage.trim() && selectedFiles.length === 0)" class="bg-[#25D366] text-white p-2.5 rounded-full hover:bg-[#1EBE5D] disabled:opacity-50 shadow-md shadow-[#25D366]/30 transition-colors mb-1 ml-1 flex items-center justify-center w-11 h-11 shrink-0">
                <svg x-show="!isSending" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" class="ml-1"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
                <svg x-show="isSending" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>
    </div>

    {{-- Yeni Grup Modalı --}}
    <div x-show="openGroupModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-transition.opacity>
        <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-100" @click.away="openGroupModal = false" x-transition.scale.origin.bottom>
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white/95 backdrop-blur-md">
                <h3 class="text-[18px] font-bold text-black">Yeni Grup veya Sohbet</h3>
                <button @click="openGroupModal = false" class="text-slate-400 hover:text-black transition-colors bg-slate-100 p-2 rounded-full">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="p-6 space-y-5 bg-white">
                <div>
                    <label class="block text-xs font-bold text-[#25D366] uppercase tracking-wider mb-2">GRUP KONUSU (DİREKT MESAJ İÇİN BOŞ BIRAKIN)</label>
                    <input type="text" x-model="newGroup.name" class="w-full border-b-2 border-slate-200 focus:border-[#25D366] focus:ring-0 px-0 py-2 text-[16px] text-black font-medium transition-colors" placeholder="Grup konusunu buraya yazın">
                </div>
                <div class="pt-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">KATILIMCILAR</label>
                    <div class="max-h-56 overflow-y-auto ios-scroll border border-slate-100 rounded-2xl bg-slate-50/50">
                        <template x-for="user in usersList" :key="user.id">
                            <label class="flex items-center gap-3 p-3 hover:bg-slate-100 cursor-pointer border-b border-slate-100 last:border-0 transition-colors">
                                <input type="checkbox" :value="user.id" x-model="newGroup.selectedUsers" class="w-5 h-5 rounded border-slate-300 text-[#25D366] focus:ring-[#25D366]">
                                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500 shrink-0" x-text="user.name.substring(0, 1)"></div>
                                <span class="text-[16px] font-semibold text-black" x-text="user.name"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <button @click="createGroup" :disabled="newGroup.selectedUsers.length === 0 || isCreatingGroup" class="w-full py-4 mt-2 bg-[#25D366] text-white font-bold text-[16px] rounded-2xl hover:bg-[#1EBE5D] disabled:opacity-50 shadow-md shadow-[#25D366]/30 transition-all active:scale-[0.98]">
                    Sohbeti Başlat
                </button>
            </div>
        </div>
    </div>

</div>

<audio id="chat-notification-sound" preload="auto"></audio>

<script>
    const popSoundData = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQQAAAAAAAD//w=='; 
    document.getElementById('chat-notification-sound').src = popSoundData;

    function chatApp() {
        const BASE = '{{ url("/") }}'.replace(/\/$/, '');
        return {
            conversations: [],
            searchQuery: '',
            activeChat: null,
            messages: [],
            newMessage: '',
            isSending: false,
            selectedFiles: [],
            
            selectMode: false,
            selectedConvIds: [],
            
            openGroupModal: false,
            usersList: [],
            newGroup: { name: '', selectedUsers: [] },
            isCreatingGroup: false,
            authUserId: {{ auth()->id() }},

            init() {
                this.fetchConversations();
                this.fetchUsersList();

                // Echo Realtime Listeners
                if (window.Echo) {
                    window.Echo.private('App.Models.User.' + this.authUserId)
                        .listen('.message.received', (e) => {
                            this.fetchConversations(true);
                            if (this.activeChat && this.activeChat.id === e.conversation_id) {
                                this.fetchMessages(this.activeChat.id, true);
                            }
                        });
                }
            },

            listenToConversation(convId) {
                if (window.Echo) {
                    window.Echo.private('conversation.' + convId)
                        .listen('.message.sent', (e) => {
                            if (this.activeChat && this.activeChat.id === convId) {
                                // Add to current messages
                                this.messages.push({
                                    id: e.id,
                                    is_mine: e.sender_id === this.authUserId,
                                    sender_name: e.sender_name,
                                    body: e.body,
                                    type: e.type,
                                    time: new Date(e.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
                                    is_read: true,
                                    attachments: e.attachments
                                });
                                this.scrollToBottom();
                            }
                            this.fetchConversations(); // Update side list
                            if (e.sender_id !== this.authUserId) {
                                this.playSound();
                            }
                        });
                }
            },

            get filteredConversations() {
                if (!this.searchQuery.trim()) return this.conversations;
                const query = this.searchQuery.toLowerCase();
                return this.conversations.filter(c => c.name?.toLowerCase().includes(query));
            },

            async fetchConversations(isSilent = false) {
                try {
                    const res = await fetch(BASE + '/chat-api/conversations', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    
                    if(data.length > this.conversations.length) {
                        data.forEach(c => {
                            if(!this.conversations.find(xc => xc.id === c.id)) {
                                this.listenToConversation(c.id);
                            }
                        });
                    }

                    this.conversations = data;
                } catch (e) {}
            },

            async fetchMessages() {
                if (!this.activeChat) return;
                try {
                    const res = await fetch(BASE + '/chat-api/conversations/' + this.activeChat.id + '/messages', {
                        headers: { 'Accept': 'application/json' }
                    });
                    this.messages = await res.json();
                    this.scrollToBottom();
                    
                    const chatInList = this.conversations.find(c => c.id === this.activeChat.id);
                    if (chatInList) chatInList.unread_count = 0;
                } catch (e) {}
            },

            handleFileSelect(e) {
                this.selectedFiles = Array.from(e.target.files);
            },
            removeFile(index) {
                this.selectedFiles.splice(index, 1);
            },

            async sendMessage() {
                if ((!this.newMessage.trim() && this.selectedFiles.length === 0) || !this.activeChat) return;
                
                this.isSending = true;
                const body = this.newMessage;
                
                let formData = new FormData();
                formData.append('body', body);
                
                this.selectedFiles.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });

                this.newMessage = ''; 
                this.selectedFiles = [];
                
                try {
                    await fetch(BASE + '/chat-api/conversations/' + this.activeChat.id + '/messages', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    });
                    
                    await this.fetchMessages();
                    await this.fetchConversations();
                } catch (e) {
                } finally {
                    this.isSending = false;
                    this.scrollToBottom();
                }
            },

            async deleteMsg(msg, forEveryone) {
                const label = forEveryone ? 'Herkesten silmek istediğinize emin misiniz?' : 'Bu mesajı silmek istediğinize emin misiniz?';
                if (!confirm(label)) return;
                try {
                    await fetch(BASE + `/chat-api/conversations/${this.activeChat.id}/messages/${msg.id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ for_everyone: forEveryone })
                    });
                    await this.fetchMessages();
                } catch (e) { console.error(e); }
            },

            async deleteConversation(conv) {
                if (!confirm(`"${conv.name}" sohbetini silmek istediğinize emin misiniz?`)) return;
                try {
                    await fetch(BASE + `/chat-api/conversations/${conv.id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    if (this.activeChat?.id === conv.id) this.activeChat = null;
                    await this.fetchConversations();
                } catch (e) { console.error(e); }
            },

            toggleConvSelection(id) {
                const idx = this.selectedConvIds.indexOf(id);
                if (idx >= 0) this.selectedConvIds.splice(idx, 1);
                else this.selectedConvIds.push(id);
            },

            async bulkDeleteConvs() {
                if (!this.selectedConvIds.length) return;
                if (!confirm(`${this.selectedConvIds.length} sohbeti silmek istediğinize emin misiniz?`)) return;
                try {
                    await fetch(BASE + '/chat-api/conversations/bulk-delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ ids: this.selectedConvIds })
                    });
                    this.selectedConvIds = [];
                    this.selectMode = false;
                    if (this.activeChat && this.selectedConvIds.includes(this.activeChat.id)) this.activeChat = null;
                    await this.fetchConversations();
                } catch (e) { console.error(e); }
            },

            selectChat(conv) {
                this.activeChat = conv;
                this.messages = []; 
                conv.unread_count = 0; 
                this.fetchMessages();
                setTimeout(() => document.querySelector('textarea')?.focus(), 100);
            },

            scrollToBottom() {
                setTimeout(() => {
                    if (this.$refs.messagesContainer) {
                        this.$refs.messagesContainer.scrollTop = this.$refs.messagesContainer.scrollHeight;
                    }
                }, 100);
            },

            playSound() {
                const audio = document.getElementById('chat-notification-sound');
                if(audio) {
                    audio.currentTime = 0;
                    audio.play().catch(e => console.log('Audio blocked:', e));
                }
            },

            async fetchUsersList() {
                try {
                    const res = await fetch(BASE + '/chat-api/users', {
                        headers: { 'Accept': 'application/json' }
                    });
                    this.usersList = await res.json();
                } catch (e) {}
            },

            async createGroup() {
                if (this.newGroup.selectedUsers.length === 0) return;
                this.isCreatingGroup = true;

                // Ensure user IDs are integers (checkbox values can be strings)
                const userIds = this.newGroup.selectedUsers.map(id => parseInt(id));
                const type = this.newGroup.name.trim() === '' && userIds.length === 1 ? 'direct' : 'group';

                try {
                    const res = await fetch(BASE + '/chat-api/conversations', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            type: type,
                            name: this.newGroup.name,
                            users: userIds
                        })
                    });
                    
                    if (res.ok) {
                        const data = await res.json();
                        this.openGroupModal = false;
                        this.newGroup.name = '';
                        this.newGroup.selectedUsers = [];
                        await this.fetchConversations();
                        
                        // Auto-open the created conversation
                        const conv = this.conversations.find(c => c.id === data.id);
                        if (conv) {
                            this.selectChat(conv);
                        }
                    }
                } catch (e) {
                    console.error('Create conversation error:', e);
                } finally {
                    this.isCreatingGroup = false;
                }
            }
        }
    }
</script>
@endsection

