import React, { useState, useEffect, useRef, useContext, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, TextInput, ActivityIndicator, KeyboardAvoidingView, Platform, Modal, Alert, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect } from '@react-navigation/native';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import * as WebBrowser from 'expo-web-browser';
import * as Haptics from 'expo-haptics';
import { Audio } from 'expo-av';
import { AuthContext } from '../context/AuthContext';
import api from '../api/axios';
import echo from '../utils/echo';

const EMOJIS = [
    '😀', '😂', '🤣', '😍', '😘', '🥰', '😊', '😉', '😎', '🤩',
    '😋', '🤔', '🤗', '😏', '😒', '😢', '😭', '😤', '🤬', '😱',
    '👍', '👎', '👏', '🙏', '💪', '❤️', '🔥', '⭐', '✅', '🎉',
    '🚗', '🚌', '🚐', '🛠️', '⛽', '📋', '📞', '💬', '👋', '🙂',
];

export default function PilotChatScreen({ navigation }) {
    const { userInfo } = useContext(AuthContext);
    const [conversations, setConversations] = useState([]);
    const [activeChat, setActiveChat] = useState(null);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [showNewChat, setShowNewChat] = useState(false);
    const [usersList, setUsersList] = useState([]);
    const [groupName, setGroupName] = useState('');
    const [selectedUsers, setSelectedUsers] = useState([]);
    const [creating, setCreating] = useState(false);
    const [showEmoji, setShowEmoji] = useState(false);
    const [selectedMsg, setSelectedMsg] = useState(null);
    const [selectMode, setSelectMode] = useState(false);
    const [selectedConvs, setSelectedConvs] = useState([]);
    const [incomingCall, setIncomingCall] = useState(null);
    const flatListRef = useRef(null);
    const msgIdCounter = useRef(Date.now());

    useFocusEffect(useCallback(() => {
        fetchConversations();

        // Listen to User's private channel for conversation list updates and Calls
        if (echo && userInfo && userInfo.id) {
            try {
                echo.private(`App.Models.User.${userInfo.id}`)
                    .listen('.message.received', (e) => {
                        fetchConversations(true);
                        if (activeChat && activeChat.id === e.conversation_id) {
                            fetchMessages(activeChat.id, true);
                        }
                    })
                    .listen('.call.event', (e) => {
                        if (e.action === 'start' && e.caller_id !== userInfo.id) {
                            setIncomingCall(e);
                            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
                        } else if (e.action === 'reject' || e.action === 'end') {
                            setIncomingCall(null);
                        }
                    });
            } catch (err) {
                console.warn('Echo listen failed:', err.message);
            }
        }

        return () => {
            if (echo && userInfo && userInfo.id) {
                try { echo.leave(`App.Models.User.${userInfo.id}`); } catch(e) {}
            }
        };
    }, [activeChat, userInfo]));

    const fetchConversations = async (s = false) => {
        if (!s) setLoading(true);
        try { const r = await api.get('/chat/conversations'); setConversations(r.data); }
        catch (e) { console.error(e); }
        finally { if (!s) setLoading(false); }
    };

    const fetchMessages = async (id, s = false) => {
        try {
            const r = await api.get(`/chat/conversations/${id}/messages`);
            setMessages(r.data);
            setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 200);
        } catch (e) { console.error(e); }
    };

    const selectChat = (c) => {
        setActiveChat(c); setMessages([]); fetchMessages(c.id);
        navigation.setOptions({ tabBarStyle: { display: 'none' } });

        // Listen to active conversation messages
        if (echo) {
            try {
                echo.private(`conversation.${c.id}`)
                    .listen('.message.sent', (e) => {
                        // If it's my own message, it was already added optimistically, but we might want to refresh anyway
                        if (e.sender_id !== userInfo.id) {
                            fetchMessages(c.id, true);
                            fetchConversations(true);
                        }
                    });
            } catch (err) {
                console.warn('Echo conversation listen failed:', err.message);
            }
        }
    };
    const goBack = () => {
        if (activeChat && echo) { try { echo.leave(`conversation.${activeChat.id}`); } catch(e) {} }
        setActiveChat(null); setShowEmoji(false);
        navigation.setOptions({ tabBarStyle: undefined });
    };

    const sendMsg = async () => {
        if (!newMessage.trim() || !activeChat) return;
        const body = newMessage; setNewMessage(''); setShowEmoji(false);
        const tempId = msgIdCounter.current++;
        const now = new Date();
        const t = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        setMessages(p => [...p, { id: tempId, is_mine: true, sender_name: userInfo?.name || '', body, type: 'text', time: t, is_read: false, is_deleted: false, attachments: [] }]);
        setTimeout(() => flatListRef.current?.scrollToEnd({ animated: true }), 50);
        try {
            await api.post(`/chat/conversations/${activeChat.id}/messages`, { body });
            fetchMessages(activeChat.id, true); fetchConversations(true);
        } catch (e) { setMessages(p => p.filter(m => m.id !== tempId)); }
    };

    const pickImage = async () => {
        const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.7 });
        if (!r.canceled && r.assets[0]) sendFile(r.assets[0]);
    };
    const takePhoto = async () => {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) { Alert.alert('İzin Gerekli', 'Kamera izni gerekli'); return; }
        const r = await ImagePicker.launchCameraAsync({ quality: 0.7 });
        if (!r.canceled && r.assets[0]) sendFile(r.assets[0]);
    };
    const pickDocument = async () => {
        try {
            const r = await DocumentPicker.getDocumentAsync({
                type: '*/*',
                copyToCacheDirectory: true,
                multiple: true
            });
            if (!r.canceled && r.assets) {
                r.assets.forEach(a => sendFile(a));
            }
        } catch (err) {
            console.error('Error picking document:', err);
        }
    };

    const sendFile = async (asset) => {
        if (!activeChat) return;
        const fd = new FormData();
        fd.append('body', '');
        const name = asset.fileName || asset.name || asset.uri.split('/').pop();
        fd.append('attachments[0]', { uri: asset.uri, name, type: asset.mimeType || 'application/octet-stream' });
        try {
            await api.post(`/chat/conversations/${activeChat.id}/messages`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            fetchMessages(activeChat.id, true); fetchConversations(true);
        } catch (e) { console.error(e); Alert.alert('Hata', 'Dosya gönderilemedi'); }
    };

    const deleteMessage = async (msg, forEveryone = false) => {
        if (!activeChat) return;
        try {
            await api.delete(`/chat/conversations/${activeChat.id}/messages/${msg.id}`, { data: { for_everyone: forEveryone } });
            fetchMessages(activeChat.id, true);
        } catch (e) { Alert.alert('Hata', e.response?.data?.message || 'Silinemedi'); }
        setSelectedMsg(null);
    };

    const deleteConversation = async (conv) => {
        Alert.alert('Sohbeti Sil', `"${conv.name}" sohbetini silmek istediğinize emin misiniz?`, [
            { text: 'İptal', style: 'cancel' },
            {
                text: 'Sil', style: 'destructive', onPress: async () => {
                    try {
                        console.log('[DELETE] Deleting conversation:', conv.id);
                        const res = await api.delete(`/chat/conversations/${conv.id}`);
                        console.log('[DELETE] Response:', res.data);
                        fetchConversations();
                    } catch (e) {
                        console.error('[DELETE] Error:', e.response?.status, e.response?.data, e.message);
                        Alert.alert('Hata', 'Sohbet silinemedi: ' + (e.response?.data?.message || e.message));
                    }
                }
            }
        ]);
    };

    const toggleConvSelect = (id) => setSelectedConvs(p => p.includes(id) ? p.filter(x => x !== id) : [...p, id]);

    const bulkDeleteConvs = () => {
        if (!selectedConvs.length) return;
        Alert.alert('Toplu Sil', `${selectedConvs.length} sohbeti silmek istediğinize emin misiniz?`, [
            { text: 'İptal', style: 'cancel' },
            {
                text: 'Sil', style: 'destructive', onPress: async () => {
                    try {
                        console.log('[BULK DELETE] IDs:', selectedConvs);
                        const res = await api.post('/chat/conversations/bulk-delete', { ids: selectedConvs });
                        console.log('[BULK DELETE] Response:', res.data);
                        setSelectedConvs([]); setSelectMode(false); fetchConversations();
                    } catch (e) {
                        console.error('[BULK DELETE] Error:', e.response?.status, e.response?.data, e.message);
                        Alert.alert('Hata', 'Toplu silme başarısız: ' + (e.response?.data?.message || e.message));
                    }
                }
            }
        ]);
    };

    const fetchUsers = async () => { try { const r = await api.get('/chat/users'); setUsersList(r.data); } catch (e) { } };
    const openNewChat = () => { fetchUsers(); setShowNewChat(true); setGroupName(''); setSelectedUsers([]); };
    const toggleUser = (id) => setSelectedUsers(p => p.includes(id) ? p.filter(x => x !== id) : [...p, id]);
    const createConversation = async () => {
        if (!selectedUsers.length) return; setCreating(true);
        const type = !groupName.trim() && selectedUsers.length === 1 ? 'direct' : 'group';
        try {
            const r = await api.post('/chat/conversations', { type, name: groupName, users: selectedUsers });
            setShowNewChat(false); await fetchConversations();
            selectChat({ id: r.data.id, name: groupName || 'Sohbet', type });
        } catch (e) { console.error(e); } finally { setCreating(false); }
    };

    // ───── CALL METHODS ─────
    const startCall = async (type) => {
        if (!activeChat) return;
        const roomId = `FiloMerkez_Call_${activeChat.id}_${Math.random().toString(36).substring(7)}`;
        try {
            await api.post(`/chat/conversations/${activeChat.id}/call`, { type, action: 'start', room_id: roomId });
            WebBrowser.openBrowserAsync(`https://meet.jit.si/${roomId}`);
        } catch (e) { Alert.alert('Hata', 'Arama başlatılamadı'); }
    };

    const handleCallResponse = async (action) => {
        if (!incomingCall) return;
        try {
            await api.post(`/chat/conversations/${incomingCall.conversation_id}/call`, { type: incomingCall.type, action, room_id: incomingCall.room_id });
            if (action === 'accept') {
                WebBrowser.openBrowserAsync(`https://meet.jit.si/${incomingCall.room_id}`);
            }
        } catch (e) { }
        setIncomingCall(null);
    };

    const filtered = search.trim() ? conversations.filter(c => c.name?.toLowerCase().includes(search.toLowerCase())) : conversations;
    const Avatar = ({ uri, name, size = 40 }) => {
        if (uri) return <Image source={{ uri }} style={{ width: size, height: size, borderRadius: size / 2 }} />;
        return <View style={[st.avatarFallback, { width: size, height: size, borderRadius: size / 2 }]}><Text style={[st.avatarFallbackText, { fontSize: size * 0.45 }]}>{name?.substring(0, 1) || '?'}</Text></View>;
    };

    // ───── CHAT DETAIL ─────
    if (activeChat) return (
        <SafeAreaView style={st.container} edges={['top', 'bottom']}>
            <View style={st.chatHeader}>
                <TouchableOpacity onPress={goBack} style={st.backBtn}><Icon name="chevron-left" size={28} color="#25D366" /></TouchableOpacity>
                <Avatar uri={activeChat.profile_photo_url} name={activeChat.name} size={40} />
                <View style={{ flex: 1, marginLeft: 10 }}>
                    <Text style={st.chatHeaderName} numberOfLines={1}>{activeChat.name}</Text>
                    <Text style={st.chatHeaderSub}>{activeChat.type === 'group' ? activeChat.participants : 'çevrimiçi'}</Text>
                </View>
                <View style={{ flexDirection: 'row', gap: 16, marginRight: 10 }}>
                    <TouchableOpacity onPress={() => startCall('video')}><Icon name="video" size={26} color="#25D366" /></TouchableOpacity>
                    <TouchableOpacity onPress={() => startCall('voice')}><Icon name="phone" size={24} color="#25D366" /></TouchableOpacity>
                </View>
            </View>

            <View style={st.chatBg}>
                <FlatList ref={flatListRef} data={messages} keyExtractor={i => i.id.toString()}
                    contentContainerStyle={{ padding: 16, paddingBottom: 8 }}
                    renderItem={({ item }) => (
                        <TouchableOpacity activeOpacity={0.8} onLongPress={() => !item.is_deleted && setSelectedMsg(item)}
                            style={[st.msgRow, item.is_mine ? st.msgRowMine : st.msgRowOther]}>
                            <View style={[st.bubble, item.is_mine ? st.bubbleMine : st.bubbleOther, item.is_deleted && { opacity: 1 }]}>
                                {activeChat.type === 'group' && !item.is_mine && <Text style={st.senderLabel}>{item.sender_name}</Text>}
                                {item.attachments?.map(att => (
                                    att.mime_type?.startsWith('image/') ?
                                        <Image key={att.id} source={{ uri: att.url }} style={st.attachImage} resizeMode="cover" /> :
                                        <TouchableOpacity key={att.id} style={st.attachBadge} onPress={() => { import('react-native').then(({ Linking }) => Linking.openURL(att.url)); }}>
                                            <Icon name="file-document-outline" size={24} color="#EF4444" style={{ marginRight: 8 }} />
                                            <View style={{ flex: 1 }}><Text style={st.attachText} numberOfLines={1}>{att.filename}</Text><Text style={{ fontSize: 10, color: '#64748B' }}>İndirmek için dokunun</Text></View>
                                        </TouchableOpacity>
                                ))}
                                {item.body ? <Text style={[st.msgText, item.is_deleted && { fontStyle: 'italic' }]}>{item.body}</Text> : null}
                                <View style={st.msgMeta}>
                                    <Text style={[st.msgTime, item.is_mine && { color: '#5E8B48' }]}>{item.time}</Text>
                                    {item.is_mine && !item.is_deleted && <Icon name={item.is_read ? "check-all" : "check"} size={14} color={item.is_read ? "#34B7F1" : "#5E8B48"} style={{ marginLeft: 2 }} />}
                                </View>
                            </View>
                        </TouchableOpacity>
                    )}
                    ListEmptyComponent={<View style={{ alignItems: 'center', marginTop: 60 }}><Icon name="chat-outline" size={48} color="#CBD5E1" /><Text style={{ color: '#94A3B8', marginTop: 12, fontSize: 14, fontWeight: '600' }}>Henüz mesaj yok</Text></View>}
                />
            </View>

            {showEmoji && <View style={st.emojiPanel}><FlatList data={EMOJIS} numColumns={8} keyExtractor={(_, i) => i.toString()} renderItem={({ item }) => (<TouchableOpacity style={st.emojiBtn} onPress={() => setNewMessage(p => p + item)}><Text style={st.emojiText}>{item}</Text></TouchableOpacity>)} /></View>}

            <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={90}>
                <View style={st.inputBar}>
                    <TouchableOpacity onPress={() => setShowEmoji(!showEmoji)} style={st.iconBtn}><Icon name={showEmoji ? 'keyboard-outline' : 'emoticon-happy-outline'} size={24} color={showEmoji ? '#25D366' : '#8696A0'} /></TouchableOpacity>
                    <TouchableOpacity onPress={() => Alert.alert('Dosya Ekle', 'Seçin', [
                        { text: '📷 Fotoğraf Çek', onPress: takePhoto },
                        { text: '🖼️ Galeriden Seç', onPress: pickImage },
                        { text: '📎 Dosya Seç', onPress: pickDocument },
                        { text: 'İptal', style: 'cancel' }
                    ])} style={st.iconBtn}><Icon name="plus" size={24} color="#8696A0" /></TouchableOpacity>
                    <View style={st.inputWrap}><TextInput style={st.input} placeholder="Bir mesaj yazın" placeholderTextColor="#8696A0" value={newMessage} onChangeText={setNewMessage} multiline maxLength={2000} /></View>
                    <TouchableOpacity style={[st.sendBtn, !newMessage.trim() && { opacity: 1 }]} onPress={sendMsg} disabled={!newMessage.trim()}><Icon name="send" size={18} color="#fff" style={{ marginLeft: 2 }} /></TouchableOpacity>
                </View>
            </KeyboardAvoidingView>

            {/* Message Action Modal */}
            <Modal visible={!!selectedMsg} transparent animationType="fade">
                <TouchableOpacity style={st.modalOverlay} activeOpacity={1} onPress={() => setSelectedMsg(null)}>
                    <View style={st.actionSheet}>
                        <Text style={st.actionTitle} numberOfLines={1}>{selectedMsg?.body || 'Dosya'}</Text>
                        {selectedMsg?.is_mine && <TouchableOpacity style={st.actionItem} onPress={() => deleteMessage(selectedMsg, true)}><Icon name="delete-forever" size={20} color="#EF4444" /><Text style={st.actionTextDanger}>Herkesten Sil</Text></TouchableOpacity>}
                        <TouchableOpacity style={st.actionItem} onPress={() => deleteMessage(selectedMsg, false)}><Icon name="delete-outline" size={20} color="#64748B" /><Text style={st.actionText}>Benden Sil</Text></TouchableOpacity>
                        <TouchableOpacity style={st.actionItem} onPress={() => setSelectedMsg(null)}><Icon name="close" size={20} color="#64748B" /><Text style={st.actionText}>İptal</Text></TouchableOpacity>
                    </View>
                </TouchableOpacity>
            </Modal>

            {/* Incoming Call Modal */}
            <Modal visible={!!incomingCall} transparent animationType="fade">
                <View style={[st.modalOverlay, { backgroundColor: 'rgba(0,0,0,0.8)' }]}>
                    <View style={{ alignItems: 'center', marginBottom: 40 }}>
                        <Icon name={incomingCall?.type === 'video' ? 'video' : 'phone'} size={60} color="#fff" />
                        <Text style={{ fontSize: 24, color: '#fff', fontWeight: 'bold', marginTop: 20 }}>{incomingCall?.caller_name}</Text>
                        <Text style={{ fontSize: 16, color: '#25D366', marginTop: 10 }}>Gelen {incomingCall?.type === 'video' ? 'Görüntülü' : 'Sesli'} Arama...</Text>
                    </View>
                    <View style={{ flexDirection: 'row', gap: 40 }}>
                        <TouchableOpacity onPress={() => handleCallResponse('reject')} style={{ backgroundColor: '#EF4444', width: 70, height: 70, borderRadius: 35, alignItems: 'center', justifyContent: 'center' }}>
                            <Icon name="phone-hangup" size={30} color="#fff" />
                        </TouchableOpacity>
                        <TouchableOpacity onPress={() => handleCallResponse('accept')} style={{ backgroundColor: '#25D366', width: 70, height: 70, borderRadius: 35, alignItems: 'center', justifyContent: 'center' }}>
                            <Icon name="phone" size={30} color="#fff" />
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>
        </SafeAreaView>
    );

    // ───── CONVERSATIONS LIST ─────
    return (
        <SafeAreaView style={st.container} edges={['top']}>
            <View style={st.listHeader}>
                {selectMode ? (
                    <>
                        <TouchableOpacity onPress={() => { setSelectMode(false); setSelectedConvs([]); }}><Text style={{ fontSize: 16, color: '#64748B', fontWeight: '600' }}>İptal</Text></TouchableOpacity>
                        <Text style={{ fontSize: 16, fontWeight: '700', color: '#000' }}>{selectedConvs.length} seçili</Text>
                        <TouchableOpacity onPress={bulkDeleteConvs} disabled={!selectedConvs.length}><Icon name="delete" size={24} color={selectedConvs.length ? '#EF4444' : '#CBD5E1'} /></TouchableOpacity>
                    </>
                ) : (
                    <>
                        <Text style={st.listTitle}>Sohbetler</Text>
                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
                            <TouchableOpacity onPress={() => setSelectMode(true)}><Text style={{ fontSize: 14, color: '#25D366', fontWeight: '600' }}>Düzenle</Text></TouchableOpacity>
                            <TouchableOpacity style={st.newChatBtn} onPress={openNewChat}><LinearGradient colors={['#25D366', '#1EBE5D']} style={st.newChatGrad}><Icon name="plus" size={22} color="#fff" /></LinearGradient></TouchableOpacity>
                        </View>
                    </>
                )}
            </View>
            <View style={st.searchBar}><Icon name="magnify" size={20} color="#8696A0" /><TextInput style={st.searchInput} placeholder="Ara" placeholderTextColor="#8696A0" value={search} onChangeText={setSearch} /></View>

            {loading ? <ActivityIndicator size="large" color="#25D366" style={{ marginTop: 60 }} /> : (
                <FlatList data={filtered} keyExtractor={i => i.id.toString()} contentContainerStyle={{ paddingBottom: 100 }}
                    renderItem={({ item }) => {
                        const isSelected = selectedConvs.includes(item.id);
                        return (
                            <TouchableOpacity style={st.convItem} onPress={() => selectMode ? toggleConvSelect(item.id) : selectChat(item)} onLongPress={() => !selectMode && deleteConversation(item)} activeOpacity={0.7}>
                                {selectMode && (
                                    <View style={[st.convCheck, isSelected && st.convCheckActive]}>
                                        {isSelected && <Icon name="check" size={14} color="#fff" />}
                                    </View>
                                )}
                                <Avatar uri={item.profile_photo_url} name={item.name} size={52} />
                                <View style={st.convInfo}>
                                    <View style={st.convRow}><Text style={st.convName} numberOfLines={1}>{item.name}</Text><Text style={[st.convTime, item.unread_count > 0 && { color: '#25D366' }]}>{item.last_message_time || ''}</Text></View>
                                    <View style={st.convRow}><Text style={st.convLastMsg} numberOfLines={1}>{item.last_message || 'Henüz mesaj yok...'}</Text>{item.unread_count > 0 && <View style={st.unreadBadge}><Text style={st.unreadText}>{item.unread_count}</Text></View>}</View>
                                </View>
                                {!selectMode && (
                                    <TouchableOpacity onPress={() => deleteConversation(item)} style={st.convDeleteBtn}>
                                        <Icon name="trash-can-outline" size={18} color="#CBD5E1" />
                                    </TouchableOpacity>
                                )}
                            </TouchableOpacity>
                        );
                    }}
                    ListEmptyComponent={<View style={{ alignItems: 'center', marginTop: 80 }}><Icon name="chat-processing-outline" size={64} color="#E2E8F0" /><Text style={{ fontSize: 18, fontWeight: '800', color: '#475569', marginTop: 16 }}>Henüz sohbet yok</Text><Text style={{ fontSize: 14, color: '#94A3B8', marginTop: 6, textAlign: 'center', paddingHorizontal: 40 }}>Mesajlaşmaya başlamak için + butonuna dokunun</Text></View>}
                />
            )}

            {/* New Chat Modal */}
            <Modal visible={showNewChat} animationType="slide" transparent>
                <View style={st.modalOverlay}><View style={st.modalContent}>
                    <View style={st.modalHeader}><Text style={st.modalTitle}>Yeni Sohbet</Text><TouchableOpacity onPress={() => setShowNewChat(false)} style={st.modalClose}><Icon name="close" size={22} color="#64748B" /></TouchableOpacity></View>
                    <View style={{ paddingHorizontal: 20, marginBottom: 12 }}><Text style={st.modalLabel}>GRUP ADI (DİREKT MESAJ İÇİN BOŞ BIRAKIN)</Text><TextInput style={st.modalInput} placeholder="Grup konusu yazın..." placeholderTextColor="#94A3B8" value={groupName} onChangeText={setGroupName} /></View>
                    <View style={{ paddingHorizontal: 20, marginBottom: 8 }}><Text style={st.modalLabel}>KATILIMCILAR</Text></View>
                    <FlatList data={usersList} keyExtractor={i => i.id.toString()} style={{ maxHeight: 300 }}
                        renderItem={({ item }) => {
                            const sel = selectedUsers.includes(item.id); return (
                                <TouchableOpacity style={st.userItem} onPress={() => toggleUser(item.id)} activeOpacity={0.7}>
                                    <View style={[st.userCheck, sel && st.userCheckActive]}>{sel && <Icon name="check" size={14} color="#fff" />}</View>
                                    <Avatar uri={item.profile_photo_url} name={item.name} size={40} />
                                    <Text style={st.userName}>{item.name}</Text>
                                </TouchableOpacity>
                            );
                        }} />
                    <View style={{ padding: 20 }}><TouchableOpacity style={[st.createBtn, !selectedUsers.length && { opacity: 1 }]} onPress={createConversation} disabled={creating || !selectedUsers.length}>{creating ? <ActivityIndicator color="#fff" /> : <Text style={st.createBtnText}>Sohbeti Başlat</Text>}</TouchableOpacity></View>
                </View></View>
            </Modal>
        </SafeAreaView>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#FFF' },
    avatarFallback: { backgroundColor: '#E2E8F0', alignItems: 'center', justifyContent: 'center' },
    avatarFallbackText: { fontWeight: '700', color: '#64748B' },
    listHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingTop: Platform.OS === 'android' ? 44 : 16, paddingBottom: 8, minHeight: 56 },
    listTitle: { fontSize: 32, fontWeight: '900', color: '#000', letterSpacing: -0.5 },
    newChatBtn: { borderRadius: 20, overflow: 'hidden', shadowColor: '#25D366', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 6 },
    newChatGrad: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
    searchBar: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#F0F2F5', marginHorizontal: 20, borderRadius: 12, paddingHorizontal: 12, height: 40, marginBottom: 8 },
    searchInput: { flex: 1, marginLeft: 8, fontSize: 15, color: '#000', fontWeight: '500' },
    convItem: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 14, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#F1F5F9' },
    convCheck: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, borderColor: '#CBD5E1', alignItems: 'center', justifyContent: 'center', marginRight: 10 },
    convCheckActive: { backgroundColor: '#EF4444', borderColor: '#EF4444' },
    convDeleteBtn: { padding: 8, marginLeft: 4 },
    convInfo: { flex: 1, marginLeft: 14 },
    convRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
    convName: { fontSize: 16, fontWeight: '700', color: '#000', flex: 1, marginRight: 8 },
    convTime: { fontSize: 12, color: '#94A3B8', fontWeight: '500' },
    convLastMsg: { fontSize: 14, color: '#667781', flex: 1, marginRight: 8 },
    unreadBadge: { backgroundColor: '#25D366', minWidth: 22, height: 22, borderRadius: 11, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
    unreadText: { color: '#fff', fontSize: 11, fontWeight: '800' },
    chatHeader: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingTop: Platform.OS === 'ios' ? 12 : 40, paddingBottom: 12, backgroundColor: '#fff', borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#E2E8F0' },
    backBtn: { marginRight: 4 },
    chatHeaderName: { fontSize: 16, fontWeight: '700', color: '#000' },
    chatHeaderSub: { fontSize: 12, color: '#25D366', fontWeight: '500' },
    chatBg: { flex: 1, backgroundColor: '#EFEFEF' },
    msgRow: { width: '100%', marginBottom: 4 },
    msgRowMine: { alignItems: 'flex-end' },
    msgRowOther: { alignItems: 'flex-start' },
    bubble: { maxWidth: '80%', paddingHorizontal: 12, paddingTop: 8, paddingBottom: 18, minWidth: 80 },
    bubbleMine: { backgroundColor: '#DCF8C6', borderRadius: 16, borderBottomRightRadius: 4 },
    bubbleOther: { backgroundColor: '#fff', borderRadius: 16, borderBottomLeftRadius: 4 },
    senderLabel: { fontSize: 12, fontWeight: '700', color: '#c0316e', marginBottom: 2 },
    msgText: { fontSize: 15, color: '#000', lineHeight: 20 },
    msgMeta: { position: 'absolute', bottom: 4, right: 10, flexDirection: 'row', alignItems: 'center' },
    msgTime: { fontSize: 10, color: '#94A3B8', fontWeight: '500' },
    attachBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(0,0,0,0.05)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, marginBottom: 4 },
    attachText: { fontSize: 11, color: '#475569', marginLeft: 4, fontWeight: '500', maxWidth: 150 },
    attachImage: { width: 200, height: 150, borderRadius: 12, marginBottom: 4 },
    inputBar: { flexDirection: 'row', alignItems: 'flex-end', padding: 8, backgroundColor: '#F0F2F5', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: '#E2E8F0' },
    iconBtn: { padding: 8, marginBottom: 2 },
    inputWrap: { flex: 1, backgroundColor: '#fff', borderRadius: 24, paddingHorizontal: 16, minHeight: 44, justifyContent: 'center', borderWidth: 1, borderColor: '#E2E8F0' },
    input: { fontSize: 15, color: '#000', maxHeight: 100, paddingVertical: 10 },
    sendBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: '#25D366', alignItems: 'center', justifyContent: 'center', marginLeft: 8 },
    emojiPanel: { backgroundColor: '#F8FAFC', borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: '#E2E8F0', paddingHorizontal: 8, paddingVertical: 8, maxHeight: 200 },
    emojiBtn: { flex: 1, alignItems: 'center', justifyContent: 'center', paddingVertical: 8 },
    emojiText: { fontSize: 26 },
    modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.5)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#fff', borderTopLeftRadius: 28, borderTopRightRadius: 28, maxHeight: '80%', paddingBottom: Platform.OS === 'ios' ? 34 : 20 },
    modalHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingVertical: 16, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#F1F5F9' },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#000' },
    modalClose: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    modalLabel: { fontSize: 10, fontWeight: '800', color: '#25D366', letterSpacing: 1, marginBottom: 8 },
    modalInput: { borderBottomWidth: 2, borderBottomColor: '#E2E8F0', fontSize: 16, fontWeight: '500', color: '#000', paddingVertical: 8 },
    userItem: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#F1F5F9' },
    userCheck: { width: 22, height: 22, borderRadius: 6, borderWidth: 2, borderColor: '#CBD5E1', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    userCheckActive: { backgroundColor: '#25D366', borderColor: '#25D366' },
    userName: { fontSize: 16, fontWeight: '600', color: '#000', marginLeft: 12 },
    createBtn: { backgroundColor: '#25D366', paddingVertical: 16, borderRadius: 16, alignItems: 'center', shadowColor: '#25D366', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 6 },
    createBtnText: { color: '#fff', fontSize: 16, fontWeight: '800' },
    actionSheet: { backgroundColor: '#fff', borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 20, paddingBottom: 40 },
    actionTitle: { fontSize: 14, color: '#94A3B8', marginBottom: 16, fontWeight: '500' },
    actionItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 14, gap: 12 },
    actionText: { fontSize: 16, fontWeight: '600', color: '#1E293B' },
    actionTextDanger: { fontSize: 16, fontWeight: '600', color: '#EF4444' },
});
