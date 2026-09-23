import React from 'react';
import { TouchableOpacity, StyleSheet } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { colors, spacing, shadow } from '../theme';

export default function Fab({ onPress, icon = 'plus', accent = 'info', visible = true, style }) {
    if (!visible) return null;
    const accentColor = colors[accent] || { 500: colors.info };

    return (
        <TouchableOpacity style={[styles.fab, { backgroundColor: accentColor[500] }, style]} onPress={onPress} activeOpacity={0.8}>
            <Icon name={icon} size={28} color="#FFFFFF" />
        </TouchableOpacity>
    );
}

const styles = StyleSheet.create({
    fab: {
        position: 'absolute',
        right: spacing.xl,
        bottom: spacing.xxl,
        width: 60,
        height: 60,
        borderRadius: 30,
        alignItems: 'center',
        justifyContent: 'center',
        ...shadow.lg,
    }
});
