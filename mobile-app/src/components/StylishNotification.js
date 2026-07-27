import React, { useState, useEffect, useRef, useImperativeHandle, forwardRef } from 'react';
import { View, Text, StyleSheet, Animated, PanResponder, TouchableOpacity, Dimensions } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

const StylishNotification = forwardRef((props, ref) => {
    const [notification, setNotification] = useState(null);
    const translateY = useRef(new Animated.Value(-150)).current;
    const opacity = useRef(new Animated.Value(0)).current;
    let hideTimeout = useRef(null);

    useImperativeHandle(ref, () => ({
        show: (title, message, data = null) => {
            setNotification({ title, message, data });
            
            // Clear any existing timeout
            if (hideTimeout.current) clearTimeout(hideTimeout.current);

            // Animate In
            Animated.parallel([
                Animated.spring(translateY, {
                    toValue: 50,
                    friction: 6,
                    tension: 40,
                    useNativeDriver: true
                }),
                Animated.timing(opacity, {
                    toValue: 1,
                    duration: 300,
                    useNativeDriver: true
                })
            ]).start();

            // Auto Hide after 4 seconds
            hideTimeout.current = setTimeout(() => {
                hide();
            }, 4000);
        }
    }));

    const hide = () => {
        Animated.parallel([
            Animated.timing(translateY, {
                toValue: -150,
                duration: 300,
                useNativeDriver: true
            }),
            Animated.timing(opacity, {
                toValue: 0,
                duration: 300,
                useNativeDriver: true
            })
        ]).start(() => {
            setNotification(null);
        });
    };

    const panResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onPanResponderMove: (evt, gestureState) => {
                if (gestureState.dy < 0) {
                    translateY.setValue(50 + gestureState.dy);
                }
            },
            onPanResponderRelease: (evt, gestureState) => {
                if (gestureState.dy < -20) {
                    hide();
                } else {
                    Animated.spring(translateY, {
                        toValue: 50,
                        friction: 6,
                        tension: 40,
                        useNativeDriver: true
                    }).start();
                }
            }
        })
    ).current;

    if (!notification) return null;

    return (
        <Animated.View
            {...panResponder.panHandlers}
            style={[
                styles.container,
                {
                    opacity: opacity,
                    transform: [{ translateY: translateY }]
                }
            ]}
        >
            <TouchableOpacity 
                activeOpacity={0.9} 
                onPress={() => {
                    hide();
                    if (props.onPress) props.onPress(notification);
                }}
                style={styles.toast}
            >
                <View style={styles.iconContainer}>
                    <MaterialCommunityIcons name="bell-ring" size={24} color="#FFF" />
                </View>
                <View style={styles.content}>
                    <Text style={styles.title} numberOfLines={1}>{notification.title}</Text>
                    <Text style={styles.message} numberOfLines={2}>{notification.message}</Text>
                </View>
            </TouchableOpacity>
        </Animated.View>
    );
});

const styles = StyleSheet.create({
    container: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        alignItems: 'center',
        zIndex: 9999,
        elevation: 999,
    },
    toast: {
        width: width * 0.9,
        backgroundColor: 'rgba(15, 23, 42, 0.95)', // Glassmorphism Dark Blue
        borderRadius: 20,
        padding: 16,
        flexDirection: 'row',
        alignItems: 'center',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.5,
        shadowRadius: 20,
        elevation: 10,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.15)',
    },
    iconContainer: {
        width: 48,
        height: 48,
        borderRadius: 24,
        backgroundColor: '#8B5CF6', // Purple Accent
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        shadowColor: '#8B5CF6',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.4,
        shadowRadius: 8,
    },
    content: {
        flex: 1,
        justifyContent: 'center',
    },
    title: {
        color: '#F8FAFC',
        fontSize: 16,
        fontWeight: '800',
        marginBottom: 4,
    },
    message: {
        color: '#94A3B8',
        fontSize: 14,
        fontWeight: '500',
        lineHeight: 20,
    }
});

export default StylishNotification;
